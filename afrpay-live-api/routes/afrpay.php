<?php

use App\Http\Controllers\AfrPayPaymentController;
use App\Http\Controllers\AfrPayWebhookController;
use Illuminate\Support\Facades\Route;

/*
| AfrPay adjusted live API routes
| Mount in RouteServiceProvider / bootstrap/app.php as needed.
*/

Route::prefix('api/afrpay')->group(function () {
    Route::get('/status', [AfrPayPaymentController::class, 'status']);
    Route::post('/go-live/approve', [AfrPayPaymentController::class, 'approveGoLive']);

    Route::post('/{code}/payments', [AfrPayPaymentController::class, 'createPayment'])
        ->where('code', 'OR001|OB003|or001|ob003');
    Route::get('/{code}/payments/{providerReference}', [AfrPayPaymentController::class, 'paymentStatus'])
        ->where('code', 'OR001|OB003|or001|ob003');
    Route::post('/{code}/refunds', [AfrPayPaymentController::class, 'refund'])
        ->where('code', 'OR001|OB003|or001|ob003');
});

Route::post('/webhooks/afrpay/{code}', [AfrPayWebhookController::class, 'handle'])
    ->where('code', 'OR001|OB003|or001|ob003')
    ->name('webhooks.afrpay');
