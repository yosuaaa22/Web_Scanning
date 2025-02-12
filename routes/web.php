<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecurityScannerController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PerformanceMetricController;
use App\Http\Controllers\WebsiteMonitorController;
use App\Http\Controllers\SecurityController;

// Public Routes
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Authenticated Routes
Route::middleware(['auth', 'security.enhanced'])->group(function () {
    // Dashboard
    Route::get('/', [SecurityScannerController::class, 'index'])->name('scanner.index');

    // Security Scanner Routes
    Route::prefix('scanner')->group(function () {
        // Scan route
        Route::post('/scan', [SecurityScannerController::class, 'scan'])
            ->name('scanner.scan')
            ->middleware(['throttle:10,1', 'security.scan']);
            Route::get('/scanner/result', [SecurityScannerController::class, 'showResult'])->name('scanner.result');
        // History routes
        Route::get('/history', [HistoryController::class, 'index'])->name('scanner.history');
        Route::get('/history/download', [HistoryController::class, 'downloadPDF'])->name('scanner.history.download');
        // routes/web.php
Route::get('/scanner/result', function() {
    return redirect()->route('scanner.index')->with('error', 'Tidak ada hasil scan yang tersedia');
})->name('scanner.result');
    });


    // Performance Monitoring
    Route::prefix('performance')->group(function () {
        Route::get('/', [PerformanceMetricController::class, 'index'])->name('performance.index');
        Route::get('/dashboard', [PerformanceMetricController::class, 'dashboard'])->name('performance.dashboard');
        Route::get('/api', [PerformanceMetricController::class, 'api'])->name('performance.api');
    });

    // Website Monitoring
    Route::prefix('monitor')->group(function () {
        Route::get('/', [WebsiteMonitorController::class, 'index'])->name('monitor.index');
        Route::post('/websites', [WebsiteMonitorController::class, 'store'])->name('monitor.store');

        Route::prefix('websites/{website}')->group(function () {
            Route::post('/check-status', [WebsiteMonitorController::class, 'checkStatus'])->name('checkStatus');
            Route::get('/status', [WebsiteMonitorController::class, 'getCachedWebsiteStatus']);
            Route::get('/ssl-certificate', [WebsiteMonitorController::class, 'getSslCertificateStatus'])->name('monitor.ssl-certificate');
            Route::get('/security-headers', [WebsiteMonitorController::class, 'getSecurityHeadersStatus'])->name('monitor.security-headers');
            Route::get('/open-ports', [WebsiteMonitorController::class, 'getOpenPortsStatus'])->name('monitor.open-ports');
        });

        Route::get('/real-time-status', [WebsiteMonitorController::class, 'getRealTimeStatus']);
    });

    // Security Management
    Route::prefix('security')->group(function () {
        Route::get('/login-attempts', [SecurityController::class, 'loginAttempts'])->name('security.login-attempts');
        Route::post('/block-ip', [SecurityController::class, 'blockIP'])->name('security.block-ip');
        Route::get('/report', [SecurityController::class, 'generateSecurityReport'])->name('security.report');
    });

    // Website Management
    Route::prefix('websites')->name('websites.')->group(function () {
        // Urutan yang benar:
        Route::get('/', [WebsiteMonitorController::class, 'index'])->name('index');
        Route::get('/create', [WebsiteMonitorController::class, 'create'])->name('create');
        Route::post('/', [WebsiteMonitorController::class, 'store'])->name('store');
        Route::get('/{website}/edit', [WebsiteMonitorController::class, 'edit'])->name('edit'); 
        Route::put('/{website}', [WebsiteMonitorController::class, 'update'])->name('update');
        Route::get('/websites', [WebsiteMonitorController::class, 'index'])->name('websites.index');
        Route::get('/{website}', [WebsiteMonitorController::class, 'show'])->name('show');
        Route::delete('/{website}', [WebsiteMonitorController::class, 'destroy'])->name('destroy');
        Route::post('/{website}/scan', [WebsiteMonitorController::class, 'scan'])->name('scan');
    });

    Route::get('/test-telegram', function() {
        try {
            $telegram = new App\Services\TelegramService();
            $response = $telegram->sendAlert(
                null, 
                "🔥 TEST NOTIFIKASI\n"
                . "Waktu: " . now()->format('Y-m-d H:i:s') . "\n"
                . "Status: Berhasil!"
            );
            
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    Route::post('/telegram/webhook', [TelegramController::class, 'handleWebhook']);
});
