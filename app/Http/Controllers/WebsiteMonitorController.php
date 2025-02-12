<?php

namespace App\Http\Controllers;
use DOMDocument;
use App\Models\Website;
use App\Jobs\WebsiteScanJob;
use App\Enums\WebsiteStatus;
use App\Services\WebsiteScanner;
use App\Services\TelegramService;
Use App\Model\scanHistori;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;


$dom = new \DOMDocument(); 
class WebsiteMonitorController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function index(Request $request)
    {
        $query = Website::with(['sslDetails', 'latestScan'])
            ->withCount([
                'scanHistori as successful_scans' => fn($q) => $q->where('status', 'success'),
                'scanHistori as failed_scans' => fn($q) => $q->where('status', 'failed')
            ])
            ->orderByRaw("
                CASE 
                    WHEN status = 'down' THEN 1 
                    WHEN status = 'unstable' THEN 2 
                    WHEN status = 'up' THEN 3 
                    ELSE 4 
                END
            ");

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('url', 'like', "%{$request->search}%");
            });
        }

        $websites = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => Website::count(),
            'up' => Website::where('status', 'up')->count(),
            'down' => Website::where('status', 'down')->count(),
            'unstable' => Website::where('status', 'unstable')->count(),
        ];

        return view('websites.index', compact('websites', 'stats'));
    }

    public function create()
    {
        return view('websites.create', [
            'intervalOptions' => [5 => '5 Menit', 15 => '15 Menit', 30 => '30 Menit', 60 => '1 Jam'],
            'defaultSettings' => config('website-monitor.default_settings')
        ]);
    }

    public function store(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $validated = $request->validate([
                    'name' => 'required|string|max:255|unique:websitess',
                    'url' => 'required|url|max:255|unique:websitess',
                    'check_interval' => 'required|in:5,15,30,60',
                    'monitoring_settings' => 'sometimes|array',
                    'notification_emails' => 'sometimes|array',
                    'notification_emails.*' => 'email'
                ]);

                $validated['url'] = $this->normalizeAndValidateUrl($validated['url']);

                $website = Website::create([
                    'name' => $validated['name'],
                    'url' => $validated['url'],
                    'check_interval' => $validated['check_interval'],
                    'monitoring_settings' => array_merge(
                        config('website-monitor.default_settings'),
                        $validated['monitoring_settings'] ?? []
                    ),
                    'notification_settings' => [
                        'emails' => $validated['notification_emails'] ?? [],
                        'telegram' => $request->telegram_chat_id ?? null
                    ]
                ]);

                $this->telegramService->sendAlert(
                    $website,
                    "🆕 Website Baru Ditambahkan\n"
                    . "Waktu: " . ($website->last_checked ?? 'Belum pernah') . "\n\n"
                    . "Nama: {$website->name}\n"
                    . "URL: {$website->url}\n"
                    . "Interval: {$website->check_interval} menit"
                );

                dispatch(new WebsiteScanJob($website->id))
                    ->onQueue('high-priority');

                return redirect()->route('websites.index', $website)
                    ->with('success', 'Website berhasil ditambahkan! Scan awal sedang berjalan...');
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Website creation failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal membuat website: ' . $e->getMessage()])->withInput();
        }
    }

    public function scan(Website $website)
{
    try {
        // 1. Lakukan HTTP request dengan timeout dan verifikasi SSL
        $response = Http::withOptions([
            'verify' => true,
            'timeout' => 30,
            'allow_redirects' => true,
        ])->get($website->url);

        // Throw exception jika response tidak sukses
        if (!$response->successful()) {
            throw new \Exception("HTTP Error: Status Code {$response->status()}");
        }

        // 2. Dapatkan info SSL dengan error handling
        $sslInfo = [];
        try {
            $sslInfo = $this->getSslCertificateInfo($website->url);
        } catch (\Exception $e) {
            Log::error('SSL Check Error: ' . $e->getMessage());
            $sslInfo = [
                'valid' => false,
                'issuer' => 'Unknown',
                'valid_from' => null,
                'valid_to' => null,
                'protocol_version' => 'TLSv1.2'
            ];
        }

        // 3. Update atau create SSL details dengan null safety
        $sslData = [
            'is_valid' => $sslInfo['valid'] ?? false,
            'valid_from' => $sslInfo['valid_from'] ?? null,
            'valid_to' => $sslInfo['valid_to'] ?? null,
            'issuer' => $sslInfo['issuer'] ?? 'Unknown',
            'protocol' => $sslInfo['protocol_version'] ?? parse_url($website->url, PHP_URL_SCHEME),
            'certificate_info' => $sslInfo
        ];

        $website->sslDetails()->updateOrCreate(
            ['website_id' => $website->id],
            $sslData
        );

        // 4. Persiapkan data scan
        $handlerStats = $response->handlerStats();
        $responseTime = ($handlerStats['total_time'] ?? 0) * 1000;
        $contentSize = strlen($response->body());
        $headers = $response->headers() ?? [];

        // 5. Update status website berdasarkan hasil
        $status = $response->successful() && ($sslData['is_valid']) ? 'up' : 'down';

        // Perbaikan disini: Tambahkan parameter $website
        $scanResults = [
            'response_time' => $responseTime,
            'status_code' => $response->status(),
            'content_size' => $contentSize,
            'headers' => $headers,
            'security_score' => $this->calculateSecurityScore($headers, $sslInfo, $website)
        ];

        $website->update([
            'status' => $status,
            'last_checked' => now(),
            'scan_results' => $scanResults
        ]);

        // 6. Buat histori scan
        $website->scanHistori()->create([
            'status' => $status,
            'scan_results' => $scanResults,
            'scanned_at' => now()
        ]);

        $vulnerabilities = $this->checkVulnerabilities($website);

        

        // 7. Persiapkan pesan

        $message = "✅ Scan Berhasil\n"

            . "Waktu: " . now()->format('Y-m-d H:i:s') . "\n"

            . "Website: {$website->name}\n"

            . "Status: " . ($status === 'up' ? 'Online' : 'Offline') . "\n"  

            . "Response Time: {$responseTime}ms\n"   

            . "Content Size: " . $this->formatBytes($contentSize) . "\n"   

            . "SSL: " . ($sslData['is_valid'] ? 'Valid' : 'Invalid') . "\n"

            . "Skor Keamanan: " . $scanResults['security_score'] . "/200\n"

            . "\n🔍 Analisis Kerentanan:\n"

            . (!empty($vulnerabilities['outdated_software']) ? "🛠️ Software Usang: " . implode(', ', $vulnerabilities['outdated_software']) . "\n" : "")

            . (count($vulnerabilities['insecure_cookies']) > 0 ? "🍪 Cookie Tidak Aman: " . count($vulnerabilities['insecure_cookies']) . " ditemukan\n" : "")

            . (!empty($vulnerabilities['mixed_content']) ? "⚠️ Mixed Content: Terdeteksi\n" : "")

            . (empty($vulnerabilities['outdated_software']) && count($vulnerabilities['insecure_cookies']) === 0 && empty($vulnerabilities['mixed_content']) ? "🛡️ Tidak ada kerentanan kritis yang ditemukan" : "");


        $this->telegramService->sendAlert($website, $message);
        // 7.1 Dapatkan data kerentanan

        $vulnerabilities = $this->checkVulnerabilities($website);

        

        // 7.2 Format pesan kerentanan

        $vulnMessages = [];
        // Software usang

        if (!empty($vulnerabilities['outdated_software'])) {

            $vulnMessages[] = "🛠️ Software Usang: " . implode(', ', $vulnerabilities['outdated_software']);

        }

        

        // Cookie tidak aman

        $insecureCookiesCount = count($vulnerabilities['insecure_cookies']);

        if ($insecureCookiesCount > 0) {

            $vulnMessages[] = "🍪 Cookie Tidak Aman: {$insecureCookiesCount} cookie ditemukan";

        }

        

        // Mixed content

        if (!empty($vulnerabilities['mixed_content'])) {

            $vulnMessages[] = "⚠️ Mixed Content: " . $vulnerabilities['mixed_content'][0];

        }

        

        // 7.3 Tambahkan ke pesan utama

        if (!empty($vulnMessages)) {

            $message .= "\n\n🔴 Kerentanan:\n" . implode("\n", $vulnMessages);

        } else {

            $message .= "\n\n🟢 Tidak ada kerentanan kritis yang ditemukan";

        }
        return back()->with('success', 'Scan berhasil dilakukan');

    } catch (\Exception $e) {
        Log::error('Scan Failed: ' . $e->getMessage(), [
            'website_id' => $website->id,
            'url' => $website->url,
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString()
        ]);

        $website->update([
            'status' => 'down',
            'last_checked' => now(),
            'scan_results' => [
                'error' => $e->getMessage(),
                'error_details' => 'Lihat log untuk detail'
            ]
        ]);

        // Buat histori scan failed
        $website->scanHistori()->create([
            'status' => 'down',
            'scan_results' => ['error' => $e->getMessage()],
            'scanned_at' => now()
        ]);

        // Kirim notifikasi error
        $errorMessage = "❌ Scan Gagal\n"
            . "Website: {$website->name}\n"
            . "Error: " . $e->getMessage();

        $this->telegramService->sendAlert($website, $errorMessage);

        return back()->withErrors([
            'error' => 'Gagal melakukan scan: ' . $e->getMessage(),
            'exception' => get_class($e)
        ]);
    }
}
// Tambahkan method helper untuk format bytes
private function formatBytes($bytes, $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

private function calculateSecurityScore(array $headers, $sslInfo, Website $website): int
{
    $score = 0;

    // 1. Validitas SSL (30 poin)
    $isValid = is_array($sslInfo) ? ($sslInfo['valid'] ?? false) : ($sslInfo->is_valid ?? false);
    if ($isValid) {
        $score += 30;
    }

    // 2. Versi Protokol (10 poin)
    $protocol = is_array($sslInfo) ? ($sslInfo['protocol_version'] ?? 'TLSv1.2') : ($sslInfo->protocol ?? 'TLSv1.2');
    if (version_compare($protocol, '1.2', '>=')) {
        $score += 10;
    }

    // 3. Header Keamanan (10 poin per header)
    $securityHeaders = [
        'strict-transport-security',
        'content-security-policy',
        'x-frame-options',
        'x-content-type-options',
        'x-xss-protection'
    ];
    
    $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
    
    foreach ($securityHeaders as $header) {
        if (isset($normalizedHeaders[$header])) {
            $score += 10;
        }
    }

    // 4. Cookie Security Flags (20 poin)
    $cookieScore = $this->calculateCookieSecurityScore($website);
    $score += $cookieScore;

    // 5. CORS Configuration (15 poin)
    $corsScore = $this->calculateCORSScore($website);
    $score += $corsScore;

    // 6. Subresource Integrity (15 poin)
    $sriScore = $this->checkSubresourceIntegrity($website);
    $score += $sriScore;

    // 7. Security.txt (10 poin)
    $securityTxtScore = $this->checkSecurityTxt($website);
    $score += $securityTxtScore;

    // 8. DNSSEC (10 poin)
    $dnssecScore = $this->checkDNSSEC($website);
    $score += $dnssecScore;

    // 9. Email Security Records (15 poin)
    $emailSecurityScore = $this->checkEmailSecurityRecords($website);
    $score += $emailSecurityScore;

    // 10. Cipher Suite (15 poin)
    $cipherScore = $this->checkCipherSuite($website);
    $score += $cipherScore;

    return min($score, 200);
}

private function calculateCookieSecurityScore(Website $website): int
{
    $insecureCookies = $this->checkInsecureCookies($website);
    $score = 20;
    
    // Kurangi 5 poin per cookie tidak aman
    $penalty = count($insecureCookies) * 5;
    return max($score - $penalty, 0);
}
private function calculateCORSScore(Website $website): int
{
    $corsConfig = $this->checkCORSConfiguration($website);
    $score = 15;
    
    if (isset($corsConfig['Access-Control-Allow-Origin'])) {
        if ($corsConfig['Access-Control-Allow-Origin'] === 'Konfigurasi CORS terlalu permisif') {
            $score -= 10;
        }
    }
    
    if (isset($corsConfig['Access-Control-Allow-Methods'])) {
        if (strpos($corsConfig['Access-Control-Allow-Methods'], 'DELETE') !== false) {
            $score -= 5;
        }
    }
    
    return max($score, 0);
}
private function checkSubresourceIntegrity(Website $website): int
{
    try {
        $response = Http::get($website->url);
        $html = $response->body();
        
        $dom = new \DOMDocument(); // Gunakan namespace global
        @$dom->loadHTML($html);
        
        $scripts = $dom->getElementsByTagName('script');
        $links = $dom->getElementsByTagName('link');
        
        $externalResources = 0;
        $withIntegrity = 0;

        foreach ($scripts as $script) {
            if ($script->hasAttribute('src') && !$script->hasAttribute('integrity')) {
                $externalResources++;
            } elseif ($script->hasAttribute('integrity')) {
                $withIntegrity++;
            }
        }

        foreach ($links as $link) {
            if ($link->getAttribute('rel') === 'stylesheet' && 
                !$link->hasAttribute('integrity')) {
                $externalResources++;
            } elseif ($link->hasAttribute('integrity')) {
                $withIntegrity++;
            }
        }

        if ($externalResources === 0) return 15;
        return (int)(15 * ($withIntegrity / $externalResources));
        
    } catch (\Exception $e) {
        return 0;
    }
}

private function checkSecurityTxt(Website $website): int
{
    try {
        $url = parse_url($website->url);
        $securityTxtUrl = $url['scheme'].'://'.$url['host'].'/.well-known/security.txt';
        
        $response = Http::timeout(5)->get($securityTxtUrl);
        
        if ($response->successful() && 
            str_contains($response->body(), 'Contact:') &&
            str_contains($response->body(), 'Expires:')) {
            return 10;
        }
        return 0;
    } catch (\Exception $e) {
        return 0;
    }
}

private function checkDNSSEC(Website $website): int
{
    $domain = parse_url($website->url, PHP_URL_HOST);
    try {
        $output = shell_exec("dig +short $domain DNSKEY");
        return !empty($output) ? 10 : 0;
    } catch (\Exception $e) {
        return 0;
    }
}

private function checkEmailSecurityRecords(Website $website): int
{
    $domain = parse_url($website->url, PHP_URL_HOST);
    $score = 0;
    
    // Check SPF
    try {
        $spf = dns_get_record($domain, DNS_TXT);
        if (str_contains(implode(' ', $spf), 'v=spf1')) $score += 5;
    } catch (\Exception $e) {}
    
    // Check DKIM
    try {
        $dkim = dns_get_record('default._domainkey.'.$domain, DNS_TXT);
        if (!empty($dkim)) $score += 5;
    } catch (\Exception $e) {}
    
    // Check DMARC
    try {
        $dmarc = dns_get_record('_dmarc.'.$domain, DNS_TXT);
        if (str_contains(implode(' ', $dmarc), 'v=DMARC1')) $score += 5;
    } catch (\Exception $e) {}
    
    return $score;
}

private function checkCipherSuite(Website $website): int
{
    try {
        $sslInfo = $this->getSslCertificateInfo($website->url);
        $weakCiphers = ['RC4', 'DES', '3DES', 'MD5', 'SHA1'];
        $score = 15;
        
        foreach ($weakCiphers as $cipher) {
            if (str_contains($sslInfo['protocol_version'], $cipher)) {
                $score -= 3;
            }
        }
        
        return max($score, 0);
    } catch (\Exception $e) {
        return 0;
    }
}
    private function getSslCertificateInfo($url): array
    {
        $default = [
            'valid' => false,
            'issuer' => 'Unknown Issuer',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'serial_number' => 'N/A',
            'protocol_version' => 'TLSv1.2',
            'error' => 'Tidak dapat memverifikasi SSL'
        ];
    
        try {
            $parsedUrl = parse_url($url);
            if (!isset($parsedUrl['host'])) {
                return $default;
            }
    
            $host = $parsedUrl['host'];
            $port = $parsedUrl['scheme'] === 'https' ? 443 : 80;
    
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
    
            $socket = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
    
            if (!$socket) {
                Log::error("SSL connection failed to {$host}:{$port} - {$errstr}");
                return $default;
            }
    
            $params = stream_context_get_params($socket);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            fclose($socket);
    
            return [
                'valid' => true,
                'issuer' => $cert['issuer']['O'] ?? $default['issuer'],
                'valid_from' => date('Y-m-d H:i:s', $cert['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $cert['validTo_time_t']),
                'serial_number' => $cert['serialNumberHex'] ?? $default['serial_number'],
                'protocol_version' => $params['options']['ssl']['protocol'] ?? $default['protocol_version'],
            ];
    
        } catch (\Exception $e) {
            Log::error('SSL check error: ' . $e->getMessage());
            return $default;
        }
    }

    public function show(Website $website)
{
    $website->load(['sslDetails', 'scanHistori' => function($query) {
        $query->latest()->take(50);
    }]);

    // Data untuk chart response time
    $responseTimes = $website->scanHistori->mapWithKeys(function($scan) {
        return [
            $scan->scanned_at->format('Y-m-d H:i') => 
                $scan->scan_results['response_time'] ?? 0
        ];
    });

    // Statistik dasar
    $stats = [
        'total' => $website->scanHistori->count(),
        'up' => $website->scanHistori->where('status', 'up')->count(),
        'down' => $website->scanHistori->where('status', 'down')->count()
    ];

    // Analisis keamanan
    $securityAnalysis = $this->getSecurityAnalysis($website);
    
    // Perubahan yang diperlukan:
    $data = [
        'website' => $website,
        'stats' => $stats,
        'responseTimes' => $responseTimes,
        'sslDetails' => $website->sslDetails,
        'securityAnalysis' => $securityAnalysis, // Diubah dari securityScore ke securityAnalysis
        'recommendations' => $this->generateRecommendations($website),
        'uptimeStats' => $this->calculateUptimeStats($website)
    ];

    return view('websites.show', $data);
}

public function edit(Website $website)

{

    return view('websites.edit', [

        'website' => $website,

        'intervalOptions' => [5 => '5 Menit', 15 => '15 Menit', 30 => '30 Menit', 60 => '1 Jam'],

        'securityHeaders' => config('website-monitor.security_headers')

    ]);

}

    public function update(Request $request, Website $website)
    {
        try {
            DB::transaction(function () use ($request, $website) {
                $validated = $request->validate([
                    'name' => 'required|string|max:255|unique:websitess,name,' . $website->id,
                    'url' => 'required|url|max:255|unique:websitess,url,' . $website->id,
                    'check_interval' => 'required|in:5,15,30,60',
                    'monitoring_settings' => 'sometimes|array',
                    'notification_emails' => 'sometimes|array',
                    'notification_emails.*' => 'email'
                ]);

                $validated['url'] = $this->normalizeAndValidateUrl($validated['url']);

                $website->update([
                    'name' => $validated['name'],
                    'url' => $validated['url'],
                    'check_interval' => $validated['check_interval'],
                    'monitoring_settings' => array_merge(
                        $website->monitoring_settings,
                        $validated['monitoring_settings'] ?? []
                    ),
                    'notification_settings' => [
                        'emails' => $validated['notification_emails'] ?? [],
                        'telegram' => $request->telegram_chat_id
                    ]
                ]);

                $this->telegramService->sendAlert(
                    $website,
                    "🔄 Website Diperbarui\n"
                    . "Waktu: " . ($website->last_checked ?? 'Belum pernah') . "\n\n"
                    . "Nama: {$website->name}\n"
                    . "Interval: {$website->check_interval} menit\n"
                    . "URL: {$website->url}"
                );

                if ($website->wasChanged('check_interval')) {
                    $this->rescheduleScans($website);
                }
            });

            return redirect()->route('websites.show', $website)
                ->with('success', 'Pengaturan website berhasil diperbarui');
                
        } catch (\Exception $e) {
            Log::error('Website update failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal memperbarui website'])->withInput();
        }
    }

    public function destroy(Website $website)
    {
        try {
            DB::transaction(function () use ($website) {
                $website->scanHistori()->delete();
                $website->sslDetails()->delete();
                Storage::delete("website_logs/{$website->id}.log");
                Cache::forget("website_stats_{$website->id}");
                $website->delete();

                $this->telegramService->sendAlert(
                    $website,
                    "🗑️ Website Dihapus\n"
                    . "Waktu: " . ($website->last_checked ?? 'Belum pernah') . "\n\n"
                    . "Nama: {$website->name}\n"
                    . "URL: {$website->url}\n"
                    
                );
            });

            return redirect()->route('websites.index')
                ->with('success', 'Website berhasil dihapus');
                
        } catch (\Exception $e) {
            Log::error('Website deletion failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal menghapus website: ' . $e->getMessage()]);
        }
    }

    private function normalizeAndValidateUrl(string $url): string
    {
        $url = Str::lower(trim($url));
        
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            throw ValidationException::withMessages(['url' => 'Format URL tidak valid']);
        }

        $host = $parsedUrl['host'];
        if (!checkdnsrr($host, 'A') && !checkdnsrr($host, 'AAAA')) {
            throw ValidationException::withMessages(['url' => 'Domain tidak valid atau tidak dapat diakses']);
        }

        try {
            Http::timeout(5)->get($url)->throw();
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['url' => 'Website tidak dapat diakses: ' . $e->getMessage()]);
        }

        return rtrim($url, '/');
    }

    private function calculateUptimeStats(Website $website): array
    {
        return Cache::remember("uptime_stats_{$website->id}", 3600, function() use ($website) {
            $scans = $website->scanHistori()
                ->where('created_at', '>=', now()->subDays(30))
                ->get();

            return [
                'uptime_24h' => $this->calculateUptimePercentage($scans->where('created_at', '>=', now()->subDay())),
                'uptime_7d' => $this->calculateUptimePercentage($scans->where('created_at', '>=', now()->subDays(7))),
                'uptime_30d' => $this->calculateUptimePercentage($scans),
                'response_time_avg' => $scans->avg('scan_results.response_time') ?? 0
            ];
        });
    }

    private function calculateUptimePercentage($scans): float
    {
        $total = $scans->count();
        if ($total === 0) return 0.0;
        
        $successful = $scans->where('status', 'success')->count();
        return round(($successful / $total) * 100, 2);
    }

    private function getSslDetails(Website $website): array
    {
        return [
            'valid_from' => $website->sslDetails->valid_from ?? null,
            'valid_to' => $website->sslDetails->valid_to ?? null,
            'issuer' => $website->sslDetails->issuer ?? null,
            'days_remaining' => Carbon::parse($website->sslDetails->valid_to ?? now())->diffInDays(now()),
            'is_valid' => $website->sslDetails->is_valid ?? false
        ];
    }

    private function analyzeSSL(Website $website): array
{
    if (!$website->sslDetails) {
        return [
            'valid' => false,
            'protocol_version' => 'TLSv1.2',
            'issuer' => 'Unknown',
            'valid_from' => null,
            'valid_to' => null
        ];
    }

    return [
        'valid' => $website->sslDetails->is_valid,
        'protocol_version' => $website->sslDetails->protocol ?? 'TLSv1.2',
        'issuer' => $website->sslDetails->issuer ?? 'Unknown',
        'valid_from' => $website->sslDetails->valid_from?->toDateTimeString(),
        'valid_to' => $website->sslDetails->valid_to?->toDateTimeString()
    ];
}
    private function analyzeHeaders(Website $website)
    {
        $headers = $website->analysis_data['headers'] ?? [];
        $requiredHeaders = config('website-monitor.security_headers');

        return collect($requiredHeaders)->mapWithKeys(function($header) use ($headers) {
            $exists = in_array($header, $headers);
            return [
                $header => [
                    'exists' => $exists,
                    'status' => $exists ? 'valid' : 'missing',
                    'recommendation' => $exists ? null : "Tambahkan header {$header}"
                ]
            ];
        })->toArray();
    }

    private function rescheduleScans(Website $website): void
    {
        $website->scheduledScans()->delete();
        $nextScan = now()->addMinutes($website->check_interval);
        dispatch(new WebsiteScanJob($website->id))
            ->delay($nextScan)
            ->onQueue('scheduled-scans');
    }

    private function getResponseTimeChartData(Website $website): array
    {
        return $website->scanHistori()
            ->latest()
            ->take(50)
            ->get()
            ->mapWithKeys(function($scan) {
                return [$scan->created_at->format('Y-m-d H:i') => $scan->scan_results['response_time'] ?? 0];
            })
            ->toArray();
    }
        // Tambahkan method berikut di dalam class WebsiteMonitorController

    /**
     * Analisis keamanan lengkap website
     */
    public function analyze(Website $website)
    {
        $website->load(['sslDetails', 'scanHistori']);
        
        return [
            'security_analysis' => $this->getSecurityAnalysis($website),
            'performance_metrics' => $this->getPerformanceMetrics($website),
            'recommendations' => $this->generateRecommendations($website)
        ];
    }

    /**
     * Analisis keamanan mendetail
     */
    private function getSecurityAnalysis(Website $website): array
{
    $analysis = [

        'ssl' => $this->analyzeSSL($website),
        'headers' => $this->analyzeHeaders($website),
        'vulnerabilities' => $this->checkVulnerabilities($website),
        'cookie_security' => $this->calculateCookieSecurityScore($website),
        'cors_config' => $this->calculateCORSScore($website),
        'subresource_integrity' => $this->checkSubresourceIntegrity($website),
        'security_txt' => $this->checkSecurityTxt($website),
        'dnssec' => $this->checkDNSSEC($website),
        'email_security' => $this->checkEmailSecurityRecords($website),
        'cipher_suite' => $this->checkCipherSuite($website),
        'performance_metrics' => $this->getPerformanceMetrics($website) // Tambahkan ini

    ];

    $analysis['overall_score'] = $this->calculateSecurityScore(
        $website->scan_results['headers'] ?? [],
        $website->sslDetails ?? [],
        $website // Tambahkan parameter website
    );

    // Detail breakdown scores
    $analysis['score_breakdown'] = [
        'ssl_valid' => $analysis['ssl']['valid'] ? 30 : 0,
        'protocol_version' => $this->getProtocolScore($website),
        'security_headers' => $this->getHeadersScore($website),
        'cookie_security' => $analysis['cookie_security'],
        'cors_security' => $analysis['cors_config'],
        'subresource_integrity' => $analysis['subresource_integrity'],
        'security_txt' => $analysis['security_txt'],
        'dnssec' => $analysis['dnssec'],
        'email_security' => $analysis['email_security'],
        'cipher_suite' => $analysis['cipher_suite'],
    ];

    return $analysis;
}

private function getProtocolScore(Website $website): int
{
    $protocol = $website->sslDetails->protocol ?? 'TLSv1.2';
    return version_compare($protocol, '1.2', '>=') ? 10 : 0;
}

private function getHeadersScore(Website $website): int
{
    $headers = $website->scan_results['headers'] ?? [];
    $securityHeaders = [
        'Strict-Transport-Security',
        'Content-Security-Policy',
        'X-Frame-Options',
        'X-Content-Type-Options',
        'X-XSS-Protection'
    ];
    
    $score = 0;
    foreach ($securityHeaders as $header) {
        if (isset($headers[$header])) {
            $score += 10;
        }
    }
    return $score;
}
    /**
     * Analisis performa website
     */
    private function getPerformanceMetrics(Website $website): array
    {
        return [
            'average_response_time' => $website->scanHistori->avg('scan_results.response_time') ?? 0,
            'uptime_percentage' => $this->calculateUptimePercentage($website->scanHistori),
            'last_30_days' => $this->calculateUptimeStats($website)
        ];
    }

    /**
     * Pemeriksaan kerentanan umum
     */
    private function checkVulnerabilities(Website $website): array
    {
        return [
            'outdated_software' => $this->checkOutdatedSoftware($website),
            'insecure_cookies' => $this->checkInsecureCookies($website),
            'mixed_content' => $this->checkMixedContent($website) ? 
                ['Ditemukan konten HTTP dalam halaman HTTPS'] : 
                []
        ];
    }

    /**
     * Rekomendasi perbaikan
     */
    private function generateRecommendations(Website $website): array
    {
        $recommendations = [];
        $securityAnalysis = $this->getSecurityAnalysis($website);
        $ssl = $securityAnalysis['ssl'];
        $headers = $securityAnalysis['headers'];
        $performance = $securityAnalysis['performance_metrics'];
       

    $performance = $securityAnalysis['performance_metrics'] ?? [

        'average_response_time' => 0,

        'uptime_30d' => 0

    ];
        // 1. SSL/TLS Recommendations
        if (!$ssl['valid']) {
            $recommendations[] = '🚨 Perbarui sertifikat SSL yang telah kadaluarsa';
        } else {
            $expiryDate = Carbon::parse($ssl['valid_to']);
            $daysRemaining = $expiryDate->diffInDays(now());
            
            if ($daysRemaining < 30) {
                $recommendations[] = "⏳ Sertifikat SSL akan kadaluarsa dalam {$daysRemaining} hari (" . $expiryDate->format('d M Y') . ")";
            }
            
            if (version_compare($ssl['protocol_version'], '1.3', '<')) {
                $recommendations[] = "🔒 Upgrade ke TLS v1.3 (versi saat ini: {$ssl['protocol_version']})";
            }
        }

        // 2. Security Headers
        $missingHeaders = array_filter($headers, fn($h) => !$h['exists']);
        foreach ($missingHeaders as $header => $data) {
            $recommendations[] = "🛡️ Tambahkan header keamanan: {$header}";
        }

        // 3. Cookie Security
        if ($securityAnalysis['cookie_security'] < 15) {
            $recommendations[] = "🍪 Tingkatkan keamanan cookie dengan Secure & HttpOnly flags";
        }

        // 4. CORS Configuration
        if ($securityAnalysis['cors_config'] < 10) {
            $recommendations[] = "🌐 Batasi CORS policy ke domain yang diperlukan saja";
        }

        // 5. Subresource Integrity
        if ($securityAnalysis['subresource_integrity'] < 10) {
            $recommendations[] = "🔗 Tambahkan SRI (Subresource Integrity) untuk resource eksternal";
        }

        // 6. Security.txt
        if (!$securityAnalysis['security_txt']) {
            $recommendations[] = "📄 Tambahkan security.txt di /.well-known/security.txt";
        }

        // 7. DNSSEC
        if (!$securityAnalysis['dnssec']) {
            $recommendations[] = "🔑 Aktifkan DNSSEC untuk proteksi DNS";
        }

        // 8. Email Security
        $emailSecurity = [
            'SPF' => false,
            'DKIM' => false,
            'DMARC' => false
        ];
        
        if (str_contains($securityAnalysis['email_security'], 'SPF')) $emailSecurity['SPF'] = true;
        if (str_contains($securityAnalysis['email_security'], 'DKIM')) $emailSecurity['DKIM'] = true;
        if (str_contains($securityAnalysis['email_security'], 'DMARC')) $emailSecurity['DMARC'] = true;
        
        foreach ($emailSecurity as $protocol => $configured) {
            if (!$configured) {
                $recommendations[] = "📧 Konfigurasikan {$protocol} record untuk email security";
            }
        }

        // 9. Cipher Suite
        if ($securityAnalysis['cipher_suite'] < 12) {
            $recommendations[] = "🔐 Gunakan cipher suite modern (contoh: AES-GCM, ChaCha20)";
        }

       
        // 10. Vulnerabilities
        foreach ($securityAnalysis['vulnerabilities'] as $type => $issues) {
            if (!empty($issues)) {
                $message = is_array($issues) 
                    ? implode(', ', $issues)
                    : (string)$issues;
                    
                $recommendations[] = "⚠️ Perbaiki kerentanan ({$type}): " . $message;
            }
        }

        // 11. Performance
        if (($performance['average_response_time'] ?? 0) > 1500) {
            $rt = number_format($performance['average_response_time'] ?? 0);
            $recommendations[] = "⚡ Optimalkan performa website (RT: {$rt}ms)";
    
        }
      
        if (($performance['uptime_30d'] ?? 0) < 99.5) {
            $uptime = number_format($performance['uptime_30d'] ?? 0, 2);
            $recommendations[] = "🔄 Tingkatkan uptime (saat ini: {$uptime}%)";
        }

        // 12. Content Security
        if ($this->checkMixedContent($website)) {
            $recommendations[] = "🔗 Perbaiki mixed content (HTTP dalam HTTPS)";
        }

        // 13. HSTS Preload
        if (!$this->checkHSTSPreload($website)) {
            $recommendations[] = "🔒 Submit HSTS ke preload list untuk proteksi maksimal";
        }

        // 14. Framework Updates
        if (!empty($securityAnalysis['vulnerabilities']['outdated_software'])) {
            $recommendations[] = "🔄 Update software: " . implode(', ', $securityAnalysis['vulnerabilities']['outdated_software']);
        }

        return array_unique($recommendations);
    }

    /**
     * Pemeriksaan konten campuran
     */
    private function checkMixedContent(Website $website): bool
    {
        try {
            $response = Http::get($website->url);
            return (bool) preg_match(
                '/http:\/\//', 
                $response->body()
            ) && parse_url($website->url, PHP_URL_SCHEME) === 'https';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Pemeriksaan cookie tidak aman
     */
    private function checkInsecureCookies(Website $website): array
    {
        $insecureCookies = [];
        foreach ($website->scan_results['headers'] ?? [] as $header => $values) {
            if (strtolower($header) === 'set-cookie') {
                foreach ((array)$values as $cookie) {
                    if (stripos($cookie, 'Secure') === false || 
                        stripos($cookie, 'HttpOnly') === false) {
                        $insecureCookies[] = $cookie;
                    }
                }
            }
        }
        return $insecureCookies;
    }

    /**
     * Pemeriksaan versi software
     */
    private function checkOutdatedSoftware(Website $website): array
{
    $outdated = [];
    $headers = array_change_key_case($website->scan_results['headers'] ?? [], CASE_LOWER);
    
    // Deteksi server software
    if (isset($headers['server'])) {
        $server = $headers['server'][0];
        
        // Apache
        if (preg_match('/Apache\/(\d+\.\d+)/', $server, $matches)) {
            if (version_compare($matches[1], '2.4', '<')) {
                $outdated[] = "Apache ({$matches[1]})";
            }
        }
        
        // Nginx
        if (preg_match('/nginx\/(\d+\.\d+)/', $server, $matches)) {
            if (version_compare($matches[1], '1.18', '<')) {
                $outdated[] = "Nginx ({$matches[1]})";
            }
        }
    }
    
    // PHP version dari header
    if (isset($headers['x-powered-by'])) {
        if (preg_match('/PHP\/(\d+\.\d+)/', $headers['x-powered-by'][0], $matches)) {
            if (version_compare($matches[1], '8.0', '<')) {
                $outdated[] = "PHP ({$matches[1]})";
            }
        }
    }
    
    return $outdated;
}

    /**
     * Validasi SSL dengan CA Bundle
     */
    private function validateSSLWithCA($url): array
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'cafile' => storage_path('cacert.pem'),
                'capture_peer_cert' => true
            ]
        ]);

        // ... (implementasi koneksi SSL yang lebih aman)
    }

    /**
     * Pengecekan HSTS preload
     */
    private function checkHSTSPreload(Website $website): bool
{
    $hstsHeader = $website->scan_results['headers']['Strict-Transport-Security'] ?? null;
    
    // Handle jika header berupa array
    if (is_array($hstsHeader)) {
        $hstsHeader = implode('; ', $hstsHeader);
    }
    
    // Regex yang lebih fleksibel untuk format header
    $pattern = '/^\s*' 
             . '(?:.*;\s*)?' 
             . 'includeSubDomains' 
             . '(?:.*;\s*)?' 
             . 'preload' 
             . '(?:.*;\s*)?' 
             . 'max-age=\d+' 
             . '(?:.*)?' 
             . '$/i';

    return $hstsHeader && preg_match($pattern, (string)$hstsHeader);
}
    /**
     * Pemeriksaan konfigurasi CORS
     */
    private function checkCORSConfiguration(Website $website): array
    {
        $corsHeaders = [
            'Access-Control-Allow-Origin' => 'Tidak ada CORS header',
            'Access-Control-Allow-Methods' => 'Metode tidak aman'
        ];
        
        foreach ($corsHeaders as $header => $message) {
            if (!isset($website->scan_results['headers'][$header])) {
                continue;
            }
            
            $value = $website->scan_results['headers'][$header][0];
            if ($header === 'Access-Control-Allow-Origin' && $value === '*') {
                $corsHeaders[$header] = 'Konfigurasi CORS terlalu permisif';
            }
        }
        
        return $corsHeaders;
    }
}

// Penutup class
