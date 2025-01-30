<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnhancedSecurityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {

        if (!$request->ajax() && !$request->hasHeader('X-Requested-With')) {
            // Lanjutkan request untuk halaman web biasa
            return $next($request);
        }
        // Cek berbagai header keamanan
        if (!$request->hasHeader('X-Requested-With')) {
            Log::warning('Potential CSRF attempt', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl()
            ]);
            abort(403, 'Permintaan tidak sah.');
        }

        // Batasi akses berdasarkan IP
        $allowedIPs = [
            '127.0.0.1',  // localhost
            '192.168.56.1',// Tambahkan IP yang diizinkan
            '10.159.235.176',
            '192.168.56.1'
        ];

        if (!in_array($request->ip(), $allowedIPs)) {
            Log::warning('Unauthorized IP access attempt', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl()
            ]);
            abort(403, 'Akses ditolak.');
        }

        // Tambahkan header keamanan
        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
