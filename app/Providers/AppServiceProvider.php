<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\EnhancedDetectionService;
use GuzzleHttp\Client;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton(EnhancedDetectionService::class, function ($app) {
            return new EnhancedDetectionService(new Client());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
{
    // Pastikan timezone default
    date_default_timezone_set('Asia/Jakarta');
}
}
