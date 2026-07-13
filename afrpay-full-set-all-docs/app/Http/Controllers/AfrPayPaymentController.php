<?php

namespace App\Http\Controllers;

use App\DTO\PspPaymentRequest;
use App\DTO\PspRefundRequest;
use App\Services\Psp\AfrPayGoLiveGate;
use App\Services\Psp\PspAdaptorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class AfrPayPaymentController extends Controller
{
    public function __construct(private readonly PspAdaptorFactory $factory)
    {
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'provider' => 'AfrPay',
            'go_live' => AfrPayGoLiveGate::status(),
            'connections' => ['OR001', 'OB003'],
        ]);
    }

    public function createPayment(Request $request, string $code): JsonResponse
    {
        $code = strtoupper($code);
        $validated = $request->validate([
            'merchant_reference' => 'required|string|max:128',
            'customer_reference' => 'required|string|max:128',
            'amount_minor' => 'required|integer|min:1',
            'currency' => 'required|string|size:3',
            'success_url' => 'required|url',
            'failure_url' => 'required|url',
            'idempotency_key' => 'required|string|max:128',
            'customer' => 'array',
            'metadata' => 'array',
        ]);

        try {
            $adaptor = $this->factory->make($code);
            $response = $adaptor->createPayment(new PspPaymentRequest(
                merchantReference: $validated['merchant_reference'],
                customerReference: $validated['customer_reference'],
                amountMinor: (int) $validated['amount_minor'],
                currency: strtoupper($validated['currency']),
                successUrl: $validated['success_url'],
                failureUrl: $validated['failure_url'],
                idempotencyKey: $validated['idempotency_key'],
                customer: $validated['customer'] ?? [],
                metadata: $validated['metadata'] ?? [],
            ));
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'failure_code' => 'ADAPTOR_ERROR',
                'failure_message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'connection' => $code,
            'status' => $response->status,
            'provider_reference' => $response->providerReference,
            'redirect_url' => $response->redirectUrl,
            'failure_code' => $response->failureCode,
            'failure_message' => $response->failureMessage,
            'raw' => $response->raw,
        ], $response->failureCode ? 422 : 200);
    }

    public function paymentStatus(string $code, string $providerReference): JsonResponse
    {
        $code = strtoupper($code);

        try {
            $response = $this->factory->make($code)->getPaymentStatus($providerReference);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'failure_code' => 'ADAPTOR_ERROR',
                'failure_message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'connection' => $code,
            'status' => $response->status,
            'provider_reference' => $response->providerReference,
            'redirect_url' => $response->redirectUrl,
            'failure_code' => $response->failureCode,
            'failure_message' => $response->failureMessage,
            'raw' => $response->raw,
        ], $response->failureCode ? 422 : 200);
    }

    public function refund(Request $request, string $code): JsonResponse
    {
        $code = strtoupper($code);
        $validated = $request->validate([
            'provider_reference' => 'required|string|max:128',
            'amount_minor' => 'required|integer|min:1',
            'currency' => 'required|string|size:3',
            'idempotency_key' => 'required|string|max:128',
            'metadata' => 'array',
        ]);

        try {
            $response = $this->factory->make($code)->refund(new PspRefundRequest(
                providerReference: $validated['provider_reference'],
                amountMinor: (int) $validated['amount_minor'],
                currency: strtoupper($validated['currency']),
                idempotencyKey: $validated['idempotency_key'],
                metadata: $validated['metadata'] ?? [],
            ));
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'failure_code' => 'ADAPTOR_ERROR',
                'failure_message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'connection' => $code,
            'status' => $response->status,
            'provider_reference' => $response->providerReference,
            'failure_code' => $response->failureCode,
            'failure_message' => $response->failureMessage,
            'raw' => $response->raw,
        ], $response->failureCode ? 422 : 200);
    }

    /**
     * Unlock live after pre-flight is green.
     * Call once with approved_by when tests are fully green.
     */
    public function approveGoLive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'approved_by' => 'required|string|max:128',
            'or001' => 'boolean',
            'ob003' => 'boolean',
            'enable_live' => 'boolean',
        ]);

        $lines = [
            'AFRPAY_TEST_APPROVED=true',
            'AFRPAY_TEST_APPROVED_BY='.$validated['approved_by'],
            'AFRPAY_TEST_APPROVED_AT='.now()->toIso8601String(),
        ];

        if ($request->boolean('or001', true)) {
            $lines[] = 'AFRPAY_OR001_TEST_APPROVED=true';
        }
        if ($request->boolean('ob003', true)) {
            $lines[] = 'AFRPAY_OB003_TEST_APPROVED=true';
        }
        if ($request->boolean('enable_live', false)) {
            $lines[] = 'AFRPAY_LIVE_ENABLED=true';
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Apply these env values on the live host, then restart PHP/workers.',
            'env' => $lines,
            'current' => AfrPayGoLiveGate::status(),
        ]);
    }
}
