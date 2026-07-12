<?php

namespace App\Services\Psp\Adaptors;

use App\DTO\PspPaymentRequest;
use App\DTO\PspPaymentResponse;
use App\DTO\PspRefundRequest;
use App\DTO\PspRefundResponse;
use App\DTO\PspWebhookResult;

/**
 * AfrPay Onramp connection OR001 — live HTTP adaptor.
 * After pre-flight test approval, set AFRPAY_LIVE_ENABLED=true and fill env credentials.
 */
class AfrPayOnrampAdaptor extends AbstractPspAdaptor
{
    public function code(): string
    {
        return 'OR001';
    }

    public function connectionType(): string
    {
        return 'onramp';
    }

    public function createPayment(PspPaymentRequest $request): PspPaymentResponse
    {
        $payload = [
            'merchant_reference' => $request->merchantReference,
            'customer_reference' => $request->customerReference,
            'amount' => [
                'value' => $request->amountMinor,
                'currency' => strtoupper($request->currency),
            ],
            'target_asset' => $request->metadata['target_asset'] ?? 'USDT',
            'target_wallet_reference' => $request->metadata['exchange_wallet_reference'] ?? null,
            'success_url' => $request->successUrl,
            'failure_url' => $request->failureUrl,
            'customer' => $request->customer,
            'metadata' => array_merge($request->metadata, [
                'readies_flow' => 'card_to_usdt_or_usdc_to_readies',
                'readies_rate_eur' => '0.10',
                'connection_code' => 'OR001',
            ]),
        ];

        $result = $this->request(
            'POST',
            (string) ($this->config['create_path'] ?? '/v1/onramp/payments'),
            $payload,
            ['Idempotency-Key' => $request->idempotencyKey]
        );

        $body = $result['json'];
        $providerReference = (string) $this->pick($body, [
            'transaction_id', 'id', 'payment_id', 'reference', 'data.transaction_id', 'data.id',
        ], '');
        $redirectUrl = $this->pick($body, [
            'redirect_url', 'checkout_url', 'payment_url', '3ds_url', 'data.redirect_url', 'data.checkout_url',
        ]);
        $providerStatus = (string) $this->pick($body, [
            'status', 'state', 'payment_status', 'data.status',
        ], $result['ok'] ? 'pending' : 'failed');

        if (! $result['ok']) {
            return new PspPaymentResponse(
                status: 'failed',
                providerReference: $providerReference !== '' ? $providerReference : null,
                redirectUrl: is_string($redirectUrl) ? $redirectUrl : null,
                raw: $result,
                failureCode: (string) $this->pick($body, ['error_code', 'code', 'error.code'], 'HTTP_'.$result['status']),
                failureMessage: (string) $this->pick($body, ['error_message', 'message', 'error.message'], 'AfrPay onramp create payment failed.')
            );
        }

        $mapped = $this->mapProviderStatus($providerStatus);
        if ($redirectUrl && $mapped === 'created') {
            $mapped = 'pending';
        }

        return new PspPaymentResponse(
            status: $mapped,
            providerReference: $providerReference !== '' ? $providerReference : null,
            redirectUrl: is_string($redirectUrl) ? $redirectUrl : null,
            raw: $result
        );
    }

    public function getPaymentStatus(string $providerReference): PspPaymentResponse
    {
        $result = $this->request(
            'GET',
            (string) ($this->config['status_path'] ?? '/v1/onramp/payments/{reference}'),
            [],
            [],
            ['reference' => $providerReference]
        );

        $body = $result['json'];
        $providerStatus = (string) $this->pick($body, [
            'status', 'state', 'payment_status', 'data.status',
        ], $result['ok'] ? 'unknown' : 'failed');

        if (! $result['ok']) {
            return new PspPaymentResponse(
                status: 'failed',
                providerReference: $providerReference,
                raw: $result,
                failureCode: (string) $this->pick($body, ['error_code', 'code'], 'HTTP_'.$result['status']),
                failureMessage: (string) $this->pick($body, ['error_message', 'message'], 'AfrPay onramp status lookup failed.')
            );
        }

        return new PspPaymentResponse(
            status: $this->mapProviderStatus($providerStatus),
            providerReference: $providerReference,
            redirectUrl: $this->pick($body, ['redirect_url', 'checkout_url', 'data.redirect_url']),
            raw: $result
        );
    }

    public function refund(PspRefundRequest $request): PspRefundResponse
    {
        $payload = [
            'provider_reference' => $request->providerReference,
            'amount' => $request->amountMinor,
            'currency' => $request->currency,
            'reason' => $request->metadata['reason'] ?? 'merchant_refund',
            'metadata' => $request->metadata,
        ];

        $result = $this->request(
            'POST',
            (string) ($this->config['refund_path'] ?? '/v1/onramp/refunds'),
            $payload,
            ['Idempotency-Key' => $request->idempotencyKey]
        );

        $body = $result['json'];
        $refundRef = (string) $this->pick($body, [
            'refund_id', 'id', 'transaction_id', 'data.refund_id', 'data.id',
        ], '');
        $providerStatus = (string) $this->pick($body, [
            'status', 'state', 'data.status',
        ], $result['ok'] ? 'pending' : 'failed');

        if (! $result['ok']) {
            return new PspRefundResponse(
                status: 'failed',
                providerReference: $request->providerReference,
                raw: $result,
                failureCode: (string) $this->pick($body, ['error_code', 'code'], 'HTTP_'.$result['status']),
                failureMessage: (string) $this->pick($body, ['error_message', 'message'], 'AfrPay onramp refund failed.')
            );
        }

        return new PspRefundResponse(
            status: $this->mapProviderStatus($providerStatus),
            providerReference: $refundRef !== '' ? $refundRef : $request->providerReference,
            raw: $result
        );
    }

    public function handleWebhook(string $rawPayload, array $headers): PspWebhookResult
    {
        if (! $this->verifyWebhook($rawPayload, $headers)) {
            return new PspWebhookResult('signature_failed', 'rejected', raw: ['payload' => $rawPayload]);
        }

        $payload = json_decode($rawPayload, true) ?: [];

        return new PspWebhookResult(
            eventType: (string) $this->pick($payload, ['event', 'event_type', 'type'], 'unknown'),
            status: $this->mapProviderStatus((string) $this->pick($payload, ['status', 'state', 'payment_status', 'data.status'], 'unknown')),
            providerReference: $this->pick($payload, ['transaction_id', 'id', 'payment_id', 'data.transaction_id']),
            merchantReference: $this->pick($payload, ['merchant_reference', 'merchant_ref', 'data.merchant_reference']),
            raw: $payload
        );
    }
}
