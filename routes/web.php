<?php

use App\Http\Controllers\PspSandboxController;
use Illuminate\Support\Facades\Route;

Route::get('/psp-sandbox', [PspSandboxController::class, 'index'])->name('psp-sandbox.index');
Route::get('/psp-sandbox/{provider}', [PspSandboxController::class, 'show'])
    ->where('provider', '[0-9]+')
    ->name('psp-sandbox.show');
