<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;
use App\Http\Middleware\EnhancedSecurityMiddleware;
use App\Http\Middleware\SecurityScanMiddleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware aliases
        $middleware->alias([
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'security.enhanced' => EnhancedSecurityMiddleware::class,
            'security.scan' => SecurityScanMiddleware::class,
        ]);

        // Register global middleware
        $middleware->web(append: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            EnhancedSecurityMiddleware::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Website interval scanning scheduler
        $schedule->command('scan:check')
            ->everyMinute()
            ->appendOutputTo(storage_path('logs/scan-check.log'))
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::channel('scan')->error('Website interval scanning failed');
            });

        // Performance monitoring schedule
        $schedule->command('monitor:performance')
            ->everyMinute()
            ->appendOutputTo(storage_path('logs/performance.log'))
            ->onFailure(function () {
                Log::error('Performance monitoring schedule failed');
            });

        // Website monitoring schedule
        $schedule->command('website:monitor')
            ->everyMinute()
            ->appendOutputTo(storage_path('logs/website-monitor.log'))
            ->onFailure(function () {
                Log::error('Website monitoring schedule failed');
            });

        // Clean up old monitoring data
        $schedule->command('monitor:cleanup')
            ->daily()
            ->appendOutputTo(storage_path('logs/cleanup.log'))
            ->onFailure(function () {
                Log::error('Monitor cleanup schedule failed');
            });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            if ($e instanceof SecurityScanException) {
                Log::channel('security')->error($e->getMessage());
            }
        });
    })
    ->create();