<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class MemoryLimitMiddleware
{
    public function handle($request, Closure $next)
    {
        // Set memory limit dinamis
        ini_set('memory_limit', '256M');

        return $next($request);
    }
}