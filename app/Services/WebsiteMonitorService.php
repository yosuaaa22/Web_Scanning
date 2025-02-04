<?php

namespace App\Services;

use App\Models\Website;
use App\Models\MonitoringLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Spatie\SslCertificate\SslCertificate;
use App\Models\SecurityScan;
use App\Jobs\WebsiteMonitorJob;
use App\Services\TelegramNotificationService;


class WebsiteMonitorService
{
    protected $client;
    protected const LOCAL_TIMEOUT = 3;
    protected const SOCKET_TIMEOUT = 1;
    protected const MAX_TIMEOUT = 30; // Define MAX_TIMEOUT constant

    public function __construct(WebsiteMonitorService $monitorService)
    {
        $this->client = new Client([
            'timeout' => self::LOCAL_TIMEOUT,
            'connect_timeout' => 2,
            'verify' => false,
            'http_errors' => false,
        ]);
    }

    public function getWebsiteStatus(Website $website)
    {
        $startTime = microtime(true);
        $vulnerabilities = [];
        $status = 'down';
        $responseTime = null;

        try {
            if (!$website->exists || empty($website->url)) {
                throw new \Exception("Invalid website configuration");
            }

            $isLocalhost = $this->isLocalEnvironment($website->url);

            // Verifikasi port untuk localhost
            if ($isLocalhost) {
                $this->verifyLocalPort($website->url);
            }

            $response = $this->performHealthCheck($website, $isLocalhost);
            $responseTime = $this->calculateResponseTime($startTime);
            $status = $this->determineStatus($response, $website);
            $status = $this->checkSSLCertificate($website->url);


            $this->createMonitoringLog($website, $status, $responseTime, $vulnerabilities);

            return [
                'status' => $status,
                'response_time' => $responseTime,
                'vulnerabilities' => $vulnerabilities,
                'last_checked_at' => now(),
            ];

            if (!empty($vulnerabilities)) {
                $message = "Vulnerabilities detected on {$website->url}:\n" . implode("\n", $vulnerabilities);
                $this->sendTelegramNotification($message);
            }
        } catch (\Exception $e) {
            return $this->handleMonitoringError($website, $e);
        }
    }

    protected function sendTelegramNotification($message)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        if (!$botToken || !$chatId) {
            Log::error('Telegram bot token or chat ID not set');
            return;
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
        ];

        try {
            Http::post($url, $data);
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram notification', ['error' => $e->getMessage()]);
        }
    }

    protected function verifyLocalPort(string $url)
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? ($parsedUrl['scheme'] === 'https' ? 443 : 80);

        // Cek koneksi socket
        $socket = @fsockopen($host, $port, $errno, $errstr, self::SOCKET_TIMEOUT);

        if (!$socket) {
            $suggestions = $this->getLocalTroubleshootingSuggestions($host, $port, $errno);
            throw new \Exception("Local port verification failed: Port $port is not accessible. $suggestions");
        }
        fclose($socket);
    }

    protected function getLocalTroubleshootingSuggestions(string $host, int $port, int $errno): string
    {
        $suggestions = [];

        if ($port === 8000) {
            $suggestions[] = "- Run 'php artisan serve' to start Laravel development server";
        }

        $suggestions[] = "- Check if any service is running on port $port using 'netstat -an | findstr :$port'";
        $suggestions[] = "- Ensure no firewall is blocking port $port";
        $suggestions[] = "- Try using a different port if $port is in use";

        if ($errno === 111) { // Connection refused
            $suggestions[] = "- The local server appears to be offline";
        } elseif ($errno === 113) { // No route to host
            $suggestions[] = "- Check your network connection";
        }

        return "\nTroubleshooting suggestions:\n" . implode("\n", $suggestions);
    }

    protected function performHealthCheck(Website $website, bool $isLocalhost = false)
    {
        try {
            // Gunakan custom options untuk localhost
            $options = $isLocalhost ? [
                'timeout' => self::LOCAL_TIMEOUT,
                'connect_timeout' => 2,
                'verify' => false,
                'allow_redirects' => false,
                'http_errors' => false,
                'curl' => [
                    CURLOPT_TCP_NODELAY => true,
                    CURLOPT_TCP_FASTOPEN => true,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ]
            ] : [];

            $response = Http::withOptions($options)
                ->withHeaders([
                    'User-Agent' => 'WebsiteMonitor/1.0',
                    'Accept' => '*/*',
                    'Connection' => 'close'
                ])
                ->get($website->url);

            return $response;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $additionalInfo = $isLocalhost ? $this->getLocalTroubleshootingSuggestions(
                parse_url($website->url, PHP_URL_HOST) ?? 'localhost',
                parse_url($website->url, PHP_URL_PORT) ?? 80,
                0
            ) : '';

            throw new \Exception("Connection failed: $error $additionalInfo");
        }
    }

    protected function getCommonLocalFixes(): array
    {
        return [
            'Check if Laravel development server is running (php artisan serve)',
            'Verify port 8000 is not in use by another application',
            'Try running on a different port (php artisan serve --port=8080)',
            'Check firewall settings for localhost connections',
            'Ensure no antivirus is blocking local connections',
            'Try clearing route cache (php artisan route:clear)'
        ];
    }

    protected function calculateResponseTime($startTime): float
    {
        return round((microtime(true) - $startTime) * 1000, 2);
    }

    protected function determineStatus($response, Website $website): string
    {
        if (!$response || !$response instanceof \Illuminate\Http\Client\Response) {
            return 'down';
        }
        return $response->successful() ? 'up' : 'down';
    }

    protected function createMonitoringLog(Website $website, $status, $responseTime, $vulnerabilities)
    {
        // Ensure status is a string or integer, not an array
        $status = is_array($status) ? json_encode($status) : (string) $status;

        // Ensure responseTime is a float
        $responseTime = (float) $responseTime;

        // If vulnerabilities is an array, convert it to JSON
        if (is_array($vulnerabilities)) {
            $vulnerabilities = json_encode($vulnerabilities);
        }

        // Log the monitoring data
        MonitoringLog::create([
            'website_id' => $website->id,
            'status' => $status,
            'response_time' => $responseTime,
            'vulnerabilities' => $vulnerabilities
        ]);
    }




    protected function isLocalEnvironment(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        return in_array($host, ['localhost', '127.0.0.1']) ||
            str_contains($host, '.local') ||
            str_contains($host, '.test');
    }

    protected function formatTimeoutError(Website $website, \Exception $e, bool $isLocalhost): string
    {
        if ($isLocalhost) {
            return sprintf(
                'Local environment connection failed for %s. Please ensure your local server is running. Error: %s',
                $website->url,
                $e->getMessage()
            );
        }

        return sprintf(
            'Connection timeout (%ds) exceeded for %s: %s',
            self::MAX_TIMEOUT,
            $website->url,
            $e->getMessage()
        );
    }

    protected function handleMonitoringError(Website $website, \Exception $e)
    {
        $errorMessage = $e->getMessage();
        $errorType = 'general_error';
        $isLocalhost = $this->isLocalEnvironment($website->url);

        // Specific handling for localhost errors
        if ($isLocalhost && str_contains($errorMessage, 'Connection timeout')) {
            $errorType = 'local_timeout';
            $errorMessage = 'Local server not responding. Please check if your local environment is running properly.';
        } else if (str_contains($errorMessage, 'Connection timeout')) {
            $errorType = 'timeout';
        }

        Log::error('Website monitoring error', [
            'website_id' => $website->id,
            'url' => $website->url,
            'is_localhost' => $isLocalhost,
            'error_type' => $errorType,
            'message' => $errorMessage
        ]);

        $this->createMonitoringLog($website, 'error', null, [
            'error_type' => $errorType,
            'error_message' => $errorMessage
        ]);

        return [
            'status' => 'error',
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'last_checked_at' => now(),
            'vulnerabilities' => []
        ];
    }

    // Add retry mechanism for failed requests
    protected function executeWithRetry(callable $callback, $maxRetries = 2)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt <= $maxRetries) {
                    Log::warning('Retrying failed request', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'error' => $e->getMessage()
                    ]);
                    sleep(2 * $attempt); // Exponential backoff
                }
            }
        }

        throw $lastException;
    }

    public function isWebsiteStatusExpired(Website $website)
    {
        $lastChecked = $website->last_checked_at;

        if (!$lastChecked) {
            return true;
        }

        // Status dianggap kadaluarsa jika lebih dari 5 menit
        return $lastChecked->diffInMinutes(now()) > 5;
    }

    protected function checkSecurityHeaders($response)
    {
        $vulnerabilities = [];

        if (!$response || !$response instanceof \Illuminate\Http\Client\Response) {
            return ['Request failed - unable to check security headers'];
        }

        $requiredHeaders = [
            'Strict-Transport-Security' => 'Missing HSTS header - vulnerable to protocol downgrade attacks',
            'X-Content-Type-Options' => 'Missing XCTO header - vulnerable to MIME-type sniffing',
            'X-Frame-Options' => 'Missing XFO header - vulnerable to clickjacking',
            'Content-Security-Policy' => 'Missing CSP header - vulnerable to XSS and injection attacks',
            'X-XSS-Protection' => 'Missing XSS Protection header',
            'Referrer-Policy' => 'Missing Referrer Policy - may leak sensitive data in referrer',
            'Permissions-Policy' => 'Missing Permissions Policy - uncontrolled feature access'
        ];

        foreach ($requiredHeaders as $header => $message) {
            if (!$response->hasHeader($header)) {
                $vulnerabilities[] = $message;
            }
        }

        return $vulnerabilities;
    }

    protected function checkSSLCertificate($url)
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            Log::error("Invalid or empty website URL.");
            return ['Invalid or empty website URL'];
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            Log::error("Unable to parse host from URL: $url");
            return ['Unable to parse host from URL'];
        }

        // Skip localhost and non-HTTPS URLs
        if (in_array($host, ['localhost', '127.0.0.1']) || str_starts_with($url, 'http://')) {
            Log::warning("Skipping SSL check for non-HTTPS or localhost URL: $url");
            return ['Skipping SSL check for non-HTTPS or localhost URL'];
        }

        $vulnerabilities = [];

        try {
            $certificate = SslCertificate::createForHostName($host, 3); // timeout in seconds

            if (!$certificate) {
                throw new \Exception("Failed to retrieve SSL certificate for $host");
            }

            if ($certificate->isExpired()) {
                $vulnerabilities[] = 'SSL certificate has expired';
            }

            if ($certificate->expiresWithin(30)) {
                $vulnerabilities[] = 'SSL certificate will expire within 30 days';
            }

            if (!$certificate->isValid()) {
                $vulnerabilities[] = 'SSL certificate is invalid';
            }

            if (!$certificate->usesSha256()) {
                $vulnerabilities[] = 'SSL certificate uses weak encryption';
            }
        } catch (\Exception $e) {
            Log::error("SSL check error for URL: $url - " . $e->getMessage());
            $vulnerabilities[] = 'Unable to verify SSL certificate: ' . $e->getMessage();
        }

        return $vulnerabilities;
    }

    protected function checkContentSecurity($response, Website $website)
    {
        $vulnerabilities = [];

        if (!$response || !$response instanceof \Illuminate\Http\Client\Response) {
            return ['Request failed - unable to check content security'];
        }

        $body = (string) $response->getBody();

        // Check for mixed content
        if (str_contains($body, 'http://') && parse_url($response->effectiveUri(), PHP_URL_SCHEME) === 'https') {
            $vulnerabilities[] = 'Mixed content detected - insecure resources loaded over HTTP';
        }

        // Check for exposed sensitive files
        $sensitiveFiles = ['.env', 'wp-config.php', 'config.php', '.git'];
        foreach ($sensitiveFiles as $file) {
            try {
                $checkResponse = Http::get($response->effectiveUri() . '/' . $file);
                if ($checkResponse->successful()) {
                    $vulnerabilities[] = "Sensitive file potentially exposed: {$file}";
                }
            } catch (\Exception $e) {
                Log::error('Website request failed', ['url' => $website->url, 'error' => $e->getMessage()]);
            }
        }

        return $vulnerabilities;
    }

    protected function checkSoftwareVersions($response)
    {
        $vulnerabilities = [];

        if (!$response || !$response instanceof \Illuminate\Http\Client\Response) {
            return ['Request failed - unable to check software versions'];
        }

        $body = (string) $response->getBody();

        // Deteksi versi WordPress
        if (str_contains($body, 'wp-content')) {
            preg_match('/WordPress (\d+\.\d+\.\d+)/', $body, $matches);
            if (isset($matches[1])) {
                $version = $matches[1];
                if (version_compare($version, '5.8', '<')) {
                    $vulnerabilities[] = "Outdated WordPress version detected: {$version}";
                }
            }
        }

        // Deteksi versi Laravel
        if (str_contains($body, 'laravel')) {
            preg_match('/Laravel (\d+\.\d+\.\d+)/', $body, $matches);
            if (isset($matches[1])) {
                $version = $matches[1];
                if (version_compare($version, '8.0', '<')) {
                    $vulnerabilities[] = "Outdated Laravel version detected: {$version}";
                }
            }
        }

        return $vulnerabilities;
    }


    protected function updateWebsiteMetrics(Website $website, $status, $responseTime)
    {
        $website->update([
            'status' => $status,
            'response_time' => $responseTime,
            'last_checked_at' => now(),
            'uptime_percentage' => $website->calculateUptimePercentage()
        ]);
    }

    protected function collectPerformanceMetrics($response)
    {
        return [
            'ttfb' => $response->transferStats?->getHandlerStat('starttransfer_time') * 1000,
            'dns_time' => $response->transferStats?->getHandlerStat('namelookup_time') * 1000,
            'connect_time' => $response->transferStats?->getHandlerStat('connect_time') * 1000,
            'ssl_time' => $response->transferStats?->getHandlerStat('pretransfer_time') * 1000,
            'total_time' => $response->transferStats?->getHandlerStat('total_time') * 1000,
        ];
    }



    public function getSecurityStatus(Website $website)
    {
        $response = $this->performHealthCheck($website);

        $securityHeaders = $this->checkSecurityHeaders($response);
        $sslDetails = $this->checkSSLCertificate($website->url);
        $contentSecurity = $this->checkContentSecurity($response, $website);

        $vulnerabilities = array_merge(
            $securityHeaders ?? [],
            $sslDetails ?? [],
            $contentSecurity ?? []
        );

        return [
            'vulnerabilities' => $vulnerabilities,
            'headers_analysis' => $securityHeaders,
            'ssl_details' => $sslDetails,
            'content_security_analysis' => $contentSecurity,
            'last_checked_at' => now(),
        ];
    }

    public function saveSecurityScan(Website $website, array $securityStatus)
    {
        if (!empty($vulnerabilities)) {
            $message = "Security vulnerabilities detected on {$website->url}:\n" . implode("\n", $vulnerabilities);
            $this->sendTelegramNotification($message);
        }

        SecurityScan::create([
            'website_id' => $website->id,
            'vulnerabilities' => json_encode($securityStatus['vulnerabilities'] ?? []),
            'headers_analysis' => json_encode($securityStatus['headers_analysis'] ?? []),
            'ssl_details' => json_encode($securityStatus['ssl_details'] ?? []),
            'content_security_analysis' => json_encode($securityStatus['content_security_analysis'] ?? [])
        ]);

        
    }

    // Di WebsiteMonitorController
    public function refreshAllStatuses()
    {
        $websiteIds = Website::pluck('id');

        foreach ($websiteIds as $websiteId) {
            WebsiteMonitorJob::dispatch($websiteId)->onQueue('low-priority');
        }

        return response()->json(['message' => 'Status refresh initiated']);
    }
}
