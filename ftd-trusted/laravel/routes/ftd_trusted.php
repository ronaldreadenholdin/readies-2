<?php

use App\Http\Controllers\TrustedListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('/ftd-trusted/status', [TrustedListController::class, 'status'])->name('ftd-trusted.status');
    Route::post('/ftd-trusted/classify', [TrustedListController::class, 'classify'])->name('ftd-trusted.classify');
    Route::post('/ftd-trusted/paid', [TrustedListController::class, 'paid'])->name('ftd-trusted.paid');
});
