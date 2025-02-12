<?php

namespace App\Jobs;

use App\Models\Website;
use App\Models\ScanHistori;
use App\Services\WebsiteScanner;
use App\Notifications\WebsiteDownNotification;
use App\Notifications\SslExpiryNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class WebsiteScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Konfigurasi job
    public $tries = 3; // Jumlah maksimal percobaan
    public $maxExceptions = 2; // Maksimal exception sebelum gagal
    public $timeout = 300; // Timeout 5 menit
    public $backoff = [60, 180]; // Interval retry (detik)

    private int $websiteId;
    private bool $isManual;
    private array $progressData;

    public function __construct(
        int $websiteId,
        bool $isManual = false
    ) {
        $this->websiteId = $websiteId;
        $this->isManual = $isManual;
        $this->progressData = [
            'status' => 'queued',
            'progress' => 0,
            'message' => 'Job dimulai'
        ];
    }

    public function handle()
    {
        $this->updateProgress('processing', 0, 'Memulai proses scanning');
        
        try {
            $website = Website::with('user')->findOrFail($this->websiteId);
            $this->checkConcurrentScans($website);

            $scanner = new WebsiteScanner($website);
            $this->updateProgress('processing', 20, 'Inisialisasi scanner');

            // Eksekusi scan dengan timeout
            $result = retry(2, function() use ($scanner) {
                return $scanner->fullScan();
            }, 100);

            $this->validateScanResult($result);
            $this->updateProgress('processing', 60, 'Menyimpan hasil scan');

            $this->updateWebsiteData($website, $result);
            $this->createScanHistory($website, $result);
            
            $this->handleNotifications($website, $result);
            $this->scheduleNextScan($website);

            $this->updateProgress('completed', 100, 'Scan berhasil');
            
            if ($this->isManual) {
                Log::channel('scans')->info("Manual scan completed for {$website->url}", $result);
            }

        } catch (\Exception $e) {
            $this->handleJobFailure($website ?? null, $e);
            throw $e; // Trigger retry mechanism
        }
    }

    private function updateProgress(string $status, int $progress, string $message): void
    {
        $this->progressData = [
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ];

        // Simpan progress ke cache atau database
        if (config('queue.default') === 'database') {
            $this->update(['progress' => $this->progressData]);
        } else {
            Cache::put("scan:progress:{$this->websiteId}", $this->progressData, 3600);
        }
    }

    private function checkConcurrentScans(Website $website): void
    {
        $lockKey = "website_scan:{$website->id}";
        $lock = Cache::lock($lockKey, 300);
        
        if (!$lock->get()) {
            throw new \Exception("Scan sedang berjalan untuk website ini");
        }
        
        $this->progressData['lock'] = $lock;
    }

    private function validateScanResult(array $result): void
    {
        $requiredKeys = ['status', 'ssl', 'headers'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $result)) {
                throw new \Exception("Hasil scan tidak valid: Key {$key} tidak ditemukan");
            }
        }
    }

    private function updateWebsiteData(Website $website, array $result): void
    {
        $website->update([
            'status' => $result['status'] ?? 'unknown',
            'last_checked' => now(),
            'analysis_data' => $result,
            'last_successful_scan' => now(),
            'scan_stats' => [
                'total_scans' => $website->scan_stats['total_scans'] + 1,
                'last_status' => $result['status']
            ]
        ]);
    }

    private function createScanHistory(Website $website, array $result): void
    {
        ScanHistori::create([
            'website_id' => $website->id,
            'scan_results' => $result,
            'scanned_at' => now(),
            'scan_duration' => $result['scan_duration'] ?? 0,
            'is_manual' => $this->isManual
        ]);

        // Cleanup old scans
        ScanHistori::where('website_id', $website->id)
            ->orderByDesc('scanned_at')
            ->skip(config('website-monitor.max_scan_history', 50))
            ->limit(100)
            ->delete();
    }

    private function handleNotifications(Website $website, array $result): void
    {
        try {
            if (($result['status'] ?? 'up') === 'down') {
                Notification::send(
                    $website->user,
                    new WebsiteDownNotification($website, $result)
                );
            }
            
            if (($result['ssl']['days_until_expiry'] ?? 365) < 7) {
                Notification::send(
                    $website->user,
                    new SslExpiryNotification(
                        $website,
                        $result['ssl']['valid_to'],
                        $result['ssl']['days_until_expiry']
                    )
                );
            }
        } catch (\Exception $e) {
            Log::channel('notifications')->error("Gagal mengirim notifikasi: {$e->getMessage()}");
        }
    }

    private function scheduleNextScan(Website $website): void
    {
        if ($this->isManual) return;

        $nextScan = now()->addMinutes($website->check_interval);
        $website->update(['next_scheduled_scan' => $nextScan]);
        
        self::dispatch($website->id)
            ->delay($nextScan)
            ->onQueue('scheduled-scans');
    }

    private function handleJobFailure(?Website $website, \Exception $e): void
    {
        $this->updateProgress('failed', 100, "Gagal: {$e->getMessage()}");
        
        if ($website) {
            $website->update([
                'last_error' => $e->getMessage(),
                'scan_stats->failed_attempts' => $website->scan_stats['failed_attempts'] + 1
            ]);
            
            Log::channel('scans')->error("Website scan failed for {$website->url}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        if ($this->attempts() >= $this->tries) {
            $this->fail($e);
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->updateProgress('failed', 100, "Job gagal setelah {$this->tries} percobaan");
        Log::channel('scans')->critical("Job scan gagal total untuk website ID {$this->websiteId}", [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}