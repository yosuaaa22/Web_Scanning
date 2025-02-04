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
