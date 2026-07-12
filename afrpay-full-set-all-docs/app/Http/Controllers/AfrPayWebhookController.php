<?php

namespace App\Http\Controllers;

use App\Services\Psp\PspAdaptorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

class AfrPayWebhookController extends Controller
{
    public function __construct(private readonly PspAdaptorFactory $factory)
    {
    }

    public function handle(Request $request, string $code): JsonResponse
    {
        $code = strtoupper($code);
        $raw = $request->getContent();
        $headers = $request->headers->all();

        try {
            $result = $this->factory->make($code)->handleWebhook($raw, $headers);
        } catch (Throwable $e) {
            Log::error('AfrPay webhook adaptor error', [
                'connection' => $code,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }

        if ($result->status === 'rejected' && $result->eventType === 'signature_failed') {
            return response()->json(['status' => 'rejected', 'reason' => 'invalid_signature'], 401);
        }

        Log::info('AfrPay webhook accepted', [
            'connection' => $code,
            'event' => $result->eventType,
            'status' => $result->status,
            'provider_reference' => $result->providerReference,
            'merchant_reference' => $result->merchantReference,
        ]);

        // Hook point: update payment ledger / Readies credit / settlement here.
        return response()->json([
            'status' => 'accepted',
            'connection' => $code,
            'event' => $result->eventType,
            'mapped_status' => $result->status,
            'provider_reference' => $result->providerReference,
            'merchant_reference' => $result->merchantReference,
        ]);
    }
}
