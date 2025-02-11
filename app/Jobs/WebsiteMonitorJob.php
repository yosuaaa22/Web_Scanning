<?php

namespace App\Jobs;

use App\Models\Website;
use App\Models\UptimeReport;
use App\Services\WebsiteMonitorService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebsiteMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 45;
    public $tries = 3;
    public $backoff = [10, 30, 60];
    protected $website;

    public function __construct(Website $website)
    {
        $this->website = $website;
    }

    // In WebsiteMonitorJob (paste-4.txt)
    public function handle(WebsiteMonitorService $monitorService, NotificationService $notificationService)
    {
        try {
            // Existing status check
            $status = $monitorService->getWebsiteStatus($this->website);

            // Retrieve previous status
            $previousStatus = Cache::get('website_status_' . $this->website->id);

            // Perform security scan
            $securityStatus = $monitorService->getSecurityStatus($this->website);

            // Save security scan
            $monitorService->saveSecurityScan($this->website, $securityStatus);

            // Existing notification logic
            $this->handleStatusChanges($status, $previousStatus, $notificationService);

            // Add specific security vulnerability notifications
            if (!empty($securityStatus['vulnerabilities'])) {
                $notificationService->sendVulnerabilityAlert(
                    $this->website,
                    $securityStatus['vulnerabilities']
                );
            }
        } catch (\Exception $e) {
            // Existing error handling
        }
    }

    protected function executeWithTimeout(callable $callback, int $timeout)
    {
        $startTime = microtime(true);

        try {
            $result = $callback();

            if ((microtime(true) - $startTime) > $timeout) {
                throw new \Exception("Operation timed out after {$timeout} seconds");
            }

            return $result;
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'timed out') !== false) {
                Log::warning('Website monitoring operation timed out', [
                    'website_id' => $this->website->id,
                    'timeout' => $timeout,
                    'actual_time' => microtime(true) - $startTime
                ]);
            }
            throw $e;
        }
    }

    protected function handleStatusChanges($newStatus, $previousStatus, $notificationService)
    {
        if (!$previousStatus) {
            return;
        }

        if ($previousStatus['status'] !== $newStatus['status']) {
            $notificationService->sendStatusChangeNotification(
                $this->website,
                $previousStatus['status'],
                $newStatus['status']
            );
        }

        $newVulnerabilities = array_diff(
            $newStatus['vulnerabilities'] ?? [],
            $previousStatus['vulnerabilities'] ?? []
        );

        if (!empty($newVulnerabilities)) {
            $notificationService->sendVulnerabilityAlert(
                $this->website,
                $newVulnerabilities
            );

            // Send high-level vulnerabilities to Telegram
            $highLevelVulnerabilities = array_filter($newVulnerabilities, function ($vulnerability) {
                return $this->isHighLevelVulnerability($vulnerability);
            });

            if (!empty($highLevelVulnerabilities)) {
                $notificationService->sendTelegramNotification(
                    $this->website,
                    "High-level vulnerabilities detected:\n" . implode("\n", $highLevelVulnerabilities)
                );
            }
        }
    }

    protected function isHighLevelVulnerability($vulnerability)
    {
        // Define high-level vulnerabilities
        $highLevelKeywords = [
            'SSL certificate has expired',
            'SSL certificate is invalid',
            'Mixed content detected',
            'Sensitive file potentially exposed',
            'Outdated WordPress version detected',
            'Outdated Laravel version detected'
        ];

        foreach ($highLevelKeywords as $keyword) {
            if (strpos($vulnerability, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function updateUptimeReports($status)
    {
        $report = UptimeReport::firstOrCreate([
            'website_id' => $this->website->id,
            'period_start' => now()->startOfDay(),
            'period_end' => now()->endOfDay(),
        ]);

        if ($status['status'] === 'down') {
            $report->increment('total_downtime_minutes', 5);
            $report->increment('incidents_count');
        }

        $report->update([
            'uptime_percentage' => $this->website->calculateUptimePercentage()
        ]);
    }

    protected function checkPerformanceIssues($status, $notificationService)
    {
        if (
            isset($status['response_time']) &&
            $status['response_time'] > $this->website->alert_threshold_response_time
        ) {
            $notificationService->sendPerformanceAlert(
                $this->website,
                'High response time detected',
                $status['response_time']
            );
        }

        if (isset($status['performance_metrics'])) {
            $metrics = $status['performance_metrics'];

            if ($metrics['ttfb'] > 500) {
                $notificationService->sendPerformanceAlert(
                    $this->website,
                    'High Time To First Byte detected',
                    $metrics['ttfb']
                );
            }
        }
    }

    protected function checkExpirations($notificationService)
    {
        if ($this->website->sslExpiresWithin(30)) {
            $notificationService->sendExpirationAlert(
                $this->website,
                'SSL Certificate',
                $this->website->ssl_expires_at
            );
        }

        if (
            $this->website->domain_expires_at &&
            $this->website->domain_expires_at->lte(now()->addDays(30))
        ) {
            $notificationService->sendExpirationAlert(
                $this->website,
                'Domain',
                $this->website->domain_expires_at
            );
        }
    }

    protected function handleMonitoringError(\Exception $e, $notificationService)
    {
        Log::error('Website monitoring job failed', [
            'website_id' => $this->website->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        Cache::put(
            'website_status_' . $this->website->id,
            [
                'status' => 'error',
                'error_message' => 'Monitoring failed: ' . $e->getMessage(),
                'last_checked_at' => now()
            ],
            now()->addSeconds($this->website->check_interval)
        );

        $notificationService->sendErrorNotification(
            $this->website,
            'Monitoring job failed: ' . $e->getMessage()
        );
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Website monitoring job failed permanently', [
            'website_id' => $this->website->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
