<?php

use App\Http\Controllers\BobCController;
use Illuminate\Support\Facades\Route;

$middleware = ['web'];
if (filter_var(env('BOB_C_REQUIRE_AUTH', false), FILTER_VALIDATE_BOOLEAN)) {
    $middleware[] = 'auth';
}

Route::middleware($middleware)->group(function () {
    Route::get('/bob-c', [BobCController::class, 'index'])->name('bob-c.index');
    Route::get('/bob-c/work', [BobCController::class, 'work'])->name('bob-c.work');
    Route::get('/bob-c/status', [BobCController::class, 'status'])->name('bob-c.status');
    Route::get('/bob-c/history', [BobCController::class, 'history'])->name('bob-c.history');
    Route::post('/bob-c/ask', [BobCController::class, 'ask'])->name('bob-c.ask');
    Route::post('/bob-c/clear', [BobCController::class, 'clear'])->name('bob-c.clear');
});
