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
        // Jika request bukan AJAX/API, langsung lanjutkan
        if (!$this->isApiRequest($request)) {
            return $next($request);
        }

        // Validasi header keamanan untuk API
        if (!$this->validateSecurityHeaders($request)) {
            Log::warning('Potential CSRF attempt', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all()
            ]);
            abort(403, 'Permintaan tidak sah.');
        }

        // Validasi IP untuk API
        if (!$this->validateClientIP($request)) {
            Log::warning('Unauthorized IP access attempt', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl()
            ]);
            abort(403, 'Akses ditolak.');
        }

        // Teruskan request ke controller
        $response = $next($request);

        // Tambahkan header keamanan
        return $this->addSecurityHeaders($response);
    }

    private function isApiRequest(Request $request): bool
    {
        return $request->ajax() || $request->hasHeader('X-Requested-With');
    }

    private function validateSecurityHeaders(Request $request): bool
    {
        $requiredHeaders = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        foreach ($requiredHeaders as $header => $expectedValue) {
            if ($request->header($header) !== $expectedValue) {
                return false;
            }
        }

        return true;
    }

    private function validateClientIP(Request $request): bool
    {
        $allowedIPs = [
            '127.0.0.1',    // localhost
            '192.168.56.1', // IP internal
            '10.159.235.176',
            '10.159.235.170'
        ];

        return in_array($request->ip(), $allowedIPs);
    }

    private function addSecurityHeaders(Response $response): Response
    {
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'self'"
        ];

        foreach ($securityHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}