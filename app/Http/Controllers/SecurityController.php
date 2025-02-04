<?php

// app/Http/Controllers/SecurityController.php
namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramNotificationService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function loginAttempts()
    {
        // Get login attempts with aggregated statistics
        $loginAttempts = LoginAttempt::select(
            'status', 
            DB::raw('COUNT(*) as total'),
            DB::raw('COUNT(DISTINCT ip_address) as unique_ips')
        )
        ->groupBy('status')
        ->get();

        // Get recent login attempts
        $recentAttempts = LoginAttempt::orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // IP Blocking statistics
        $blockedIPs = LoginAttempt::where('is_blocked', true)
            ->select('ip_address', 'location', 'created_at')
            ->distinct()
            ->get();

        return view('security.login-attempts', [
            'loginAttempts' => $loginAttempts,
            'recentAttempts' => $recentAttempts,
            'blockedIPs' => $blockedIPs
        ]);
    }

    public function blockIP(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip'
        ]);

        // Block IP in login attempts
        LoginAttempt::where('ip_address', $validated['ip_address'])
            ->update(['is_blocked' => true]);

        // Send Telegram notification
        $this->telegramService->sendSecurityAlert(
            "🚫 IP Address Blocked\n" .
            "IP: {$validated['ip_address']}\n" .
            "Blocked at: " . now()->format('Y-m-d H:i:s')
        );

        return response()->json([
            'message' => 'IP address blocked successfully',
            'ip' => $validated['ip_address']
        ]);
    }

    public function generateSecurityReport()
    {
        // Comprehensive security report generation
        $report = [
            'login_attempts' => $this->getLoginAttemptsSummary(),
            'blocked_ips' => $this->getBlockedIPsSummary(),
            'vulnerability_scan' => $this->performVulnerabilityScan()
        ];

        // Option to send report via email or Telegram
        $this->sendSecurityReportNotification($report);

        return response()->json($report);
    }

    private function getLoginAttemptsSummary()
    {
        return [
            'total_attempts' => LoginAttempt::count(),
            'failed_attempts' => LoginAttempt::where('status', 'failed')->count(),
            'unique_ips' => LoginAttempt::distinct('ip_address')->count(),
            'top_countries' => LoginAttempt::select(
                    'location', 
                    DB::raw('COUNT(*) as attempt_count')
                )
                ->groupBy('location')
                ->orderBy('attempt_count', 'desc')
                ->limit(5)
                ->get()
        ];
    }

    private function getBlockedIPsSummary()
    {
        return [
            'total_blocked' => LoginAttempt::where('is_blocked', true)->count(),
            'blocked_ips' => LoginAttempt::where('is_blocked', true)
                ->select('ip_address', 'location', 'created_at')
                ->distinct()
                ->get()
        ];
    }

    private function performVulnerabilityScan()
    {
        // Simulate a basic vulnerability scan
        $websites = \App\Models\Website::all();
        $vulnerabilities = [];

        foreach ($websites as $website) {
            $websiteVulns = $this->checkWebsiteSecurity($website);
            if (!empty($websiteVulns)) {
                $vulnerabilities[$website->name] = $websiteVulns;
            }
        }

        return $vulnerabilities;
    }

    private function checkWebsiteSecurity($website)
    {
        $vulnerabilities = [];

        // Basic security checks
        try {
            $response = \Illuminate\Support\Facades\Http::get($website->url);
            
            // Check for missing security headers
            $headers = $response->headers();
            $requiredHeaders = [
                'Strict-Transport-Security' => 'HSTS missing',
                'X-Content-Type-Options' => 'MIME type sniffing protection missing',
                'X-Frame-Options' => 'Clickjacking protection missing',
                'Content-Security-Policy' => 'CSP missing'
            ];

            foreach ($requiredHeaders as $header => $message) {
                if (!isset($headers[$header])) {
                    $vulnerabilities[] = $message;
                }
            }

            // SSL/TLS check (simplified)
            if (!$response->successful()) {
                $vulnerabilities[] = 'Potential SSL/TLS configuration issue';
            }

        } catch (\Exception $e) {
            $vulnerabilities[] = 'Unable to perform security scan';
        }

        return $vulnerabilities;
    }

    private function sendSecurityReportNotification($report)
    {
        // Prepare report message
        $message = "🔒 Security Report 🔒\n\n";
        $message .= "Login Attempts: {$report['login_attempts']['total_attempts']}\n";
        $message .= "Failed Attempts: {$report['login_attempts']['failed_attempts']}\n";
        $message .= "Blocked IPs: " . count($report['blocked_ips']['blocked_ips']) . "\n";
        $message .= "Vulnerabilities Found: " . count($report['vulnerability_scan']) . "\n";

        // Send via Telegram
        $this->telegramService->sendSecurityAlert($message);

        // Optionally send via email (would require email service setup)
    }
}
