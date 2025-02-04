<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecurityScannerController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PerformanceMetricController;
// Tambahkan controller lain jika diperlukan

// Route untuk login
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Route dengan proteksi
Route::middleware(['auth', 'security.enhanced'])->group(function () {
    Route::get('/', [SecurityScannerController::class, 'index'])->name('scanner.index');

    Route::post('scanner/scan', [SecurityScannerController::class, 'scan'])
         ->name('scanner.scan')
         ->middleware(['throttle:10,1', 'security.scan']);

    Route::get('/history', [HistoryController::class, 'index'])->name('scanner.history');
    Route::get('/scanner/history/download', [HistoryController::class, 'downloadPDF'])->name('scanner.history.download');

    // Route untuk Monitoring Logs
   
    // routes/web.php
    

    
    Route::get('/performance', [PerformanceMetricController::class, 'index'])->name('performance.index');
    
    Route::get('/performance/dashboard', [PerformanceMetricController::class, 'dashboard'])->name('performance.dashboard');
    Route::get('/performance/api', [PerformanceMetricController::class, 'api'])->name('performance.api');
});


use App\Http\Controllers\WebsiteMonitorController;

Route::get('/monitor', [WebsiteMonitorController::class, 'index'])->name('monitor.index');
Route::post('/websites', [WebsiteMonitorController::class, 'store'])->name('monitor.store');
Route::post('/websites/{website}/check-status', [WebsiteMonitorController::class, 'checkStatus'])->name('checkStatus');
Route::get('/websites/{website}/status', [WebsiteMonitorController::class, 'getCachedWebsiteStatus']);
Route::get('/monitor/real-time-status', [WebsiteMonitorController::class, 'getRealTimeStatus']);

Route::get('/websites/{website}/ssl-certificate', [WebsiteMonitorController::class, 'getSslCertificateStatus'])->name('monitor.ssl-certificate');
Route::get('/websites/{website}/security-headers', [WebsiteMonitorController::class, 'getSecurityHeadersStatus'])->name('monitor.security-headers');
Route::get('/websites/{website}/open-ports', [WebsiteMonitorController::class, 'getOpenPortsStatus'])->name('monitor.open-ports');
// Route::get('/security-report', [WebsiteMonitorController::class, 'generateSecurityReport'])->name('security.report');


// Route::post('/websites/{id}/check-status', [WebsiteMonitorController::class, 'checkStatus']);

use App\Http\Controllers\SecurityController;

Route::prefix('security')->group(function () {
    Route::get('/login-attempts', [SecurityController::class, 'loginAttempts'])
        ->name('security.login-attempts');
    
    Route::post('/block-ip', [SecurityController::class, 'blockIP'])
        ->name('security.block-ip');
    
    Route::get('/report', [SecurityController::class, 'generateSecurityReport'])
        ->name('security.report');
});






