<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SecurityLoggingService
{
    public function logScanActivity($url, $result)
    {
        // Log ke file berbeda
        $logPath = 'security_scans/' . date('Y-m-d') . '_scan.log';

        $logEntry = json_encode([
            'timestamp' => now(),
            'url' => $url,
            'risk_level' => $result['risk_level'] ?? 'Unknown',
            'confidence' => $result['confidence_score'] ?? 0
        ]) . PHP_EOL;

        Storage::append($logPath, $logEntry);

        // Log ke system log
        Log::channel('security')->info('URL Scan', [
            'url' => $url,
            'risk_assessment' => $result
        ]);
    }

    public function alertHighRisk($url, $result)
    {
        if (($result['risk_level'] ?? '') === 'Tinggi') {
            // Kirim email atau notifikasi
            // Implementasi notifikasi bisa ditambahkan nanti
        }
    }
}
