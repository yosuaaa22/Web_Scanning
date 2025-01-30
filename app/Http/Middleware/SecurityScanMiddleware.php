<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SecurityScanMiddleware
{
    public function handle(Request $request, Closure $next): Response
{
    $validator = Validator::make($request->all(), [
        'url' => [
            'required',
            'url',
            'max:255',
            'regex:/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/',
            function($attribute, $value, $fail) {
                $unsafeDomains = [
                    'localhost', '127.0.0.1', 'example.com',
                    'test.com', 'invalid', 'localhost.localdomain'
                ];

                $parsedUrl = parse_url($value);
                $host = $parsedUrl['host'] ?? '';

                if (in_array($host, $unsafeDomains) || filter_var(gethostbyname($host), FILTER_VALIDATE_IP) === false) {
                    $fail('URL yang Anda masukkan tidak aman atau tidak valid.');
                }
            }
        ]
    ]);

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    Log::info('Security Scan Attempt', [
        'url' => $request->input('url'),
        'ip' => $request->ip(),
        'timestamp' => now()
    ]);

    return $next($request);
}
}
