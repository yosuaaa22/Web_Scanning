<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecurityScannerController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LoginController;

// Login routes
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware(['auth', 'security.enhanced'])->group(function () {
    Route::get('/', [SecurityScannerController::class, 'index'])->name('scanner.index');

    Route::post('scanner/scan', [SecurityScannerController::class, 'scan'])
         ->name('scanner.scan')
         ->middleware(['throttle:10,1', 'security.scan']);  // Using the SecurityScanMiddleware

    Route::get('/history', [HistoryController::class, 'index'])->name('scanner.history');
    Route::get('/scanner/history/download', [HistoryController::class, 'downloadPDF'])->name('scanner.history.download');
});
