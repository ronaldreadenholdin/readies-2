<?php

use App\Http\Controllers\PspSandboxController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/pre-flight-test', [PspSandboxController::class, 'preFlightTest'])->name('pre-flight-test');

Route::get('/psp-sandbox', [PspSandboxController::class, 'index'])->name('psp-sandbox.index');
Route::get('/psp-sandbox/run/{pspCode}', [PspSandboxController::class, 'runTest'])->name('psp-sandbox.run');
Route::post('/psp-sandbox/golive/{pspCode}', [PspSandboxController::class, 'goLive'])->name('psp-sandbox.golive');
Route::get('/psp-sandbox/{provider}', [PspSandboxController::class, 'show'])
    ->where('provider', '[0-9]+')
    ->name('psp-sandbox.show');

Route::post('/webhooks/fbls', function (Request $request) {
    return response()->json(['status' => 'accepted', 'psp' => 'fbls']);
})->middleware('verify.psp:fbls')->name('webhooks.fbls');

Route::post('/webhooks/xcore', function (Request $request) {
    return response()->json(['status' => 'accepted', 'psp' => 'xcore']);
})->middleware('verify.psp:xcore')->name('webhooks.xcore');
