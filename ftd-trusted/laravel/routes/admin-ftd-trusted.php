<?php

use App\Http\Controllers\Admin\FtdTrustedAdminController;
use Illuminate\Support\Facades\Route;

/*
 * 0609 admin backend. Put this file (or its contents) inside the existing
 * admin auth middleware group that already wraps layouts.adminpanel.
 * Merchants do not upload. Staff upload the list for a merchant.
 */
Route::prefix('admin/ftd-trusted')->name('admin.ftd-trusted.')->group(function () {
    Route::get('/', [FtdTrustedAdminController::class, 'index'])->name('index');
    Route::post('/classify', [FtdTrustedAdminController::class, 'classify'])->name('classify');
    Route::post('/paid', [FtdTrustedAdminController::class, 'paid'])->name('paid');
    Route::post('/upload', [FtdTrustedAdminController::class, 'upload'])->name('upload');
});
