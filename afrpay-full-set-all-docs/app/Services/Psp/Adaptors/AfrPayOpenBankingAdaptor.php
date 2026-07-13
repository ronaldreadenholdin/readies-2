<?php

namespace App\Services\Psp\Adaptors;

use App\DTO\PspPaymentRequest;
use App\DTO\PspPaymentResponse;
use App\DTO\PspRefundRequest;
use App\DTO\PspRefundResponse;
use App\DTO\PspWebhookResult;

/**
 * AfrPay Open Banking connection OB003 — live HTTP adaptor.
 * Separate lifecycle from OR001. Enable only after OB003 pre-flight is green.
 */
class AfrPayOpenBankingAdaptor extends AbstractPspAdaptor
{
    public function code(): string
    {
        return 'OB003';
    }

    public function connectionType(): string
    {
        return 'open_banking';
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
            'success_url' => $request->successUrl,
            'failure_url' => $request->failureUrl,
            'bank_id' => $request->metadata['bank_id'] ?? null,
            'customer' => $request->customer,
            'metadata' => array_merge($request->metadata, [
                'connection_code' => 'OB003',
            ]),
        ];

        $result = $this->request(
            'POST',
            (string) ($this->config['create_path'] ?? '/v1/open-banking/payments'),
            $payload,
            ['Idempotency-Key' => $request->idempotencyKey]
        );

        $body = $result['json'];
        $providerReference = (string) $this->pick($body, [
            'payment_id', 'transaction_id', 'id', 'consent_id', 'data.payment_id', 'data.id',
        ], '');
        $redirectUrl = $this->pick($body, [
            'redirect_url', 'authorization_url', 'bank_url', 'data.redirect_url', 'data.authorization_url',
        ]);
        $providerStatus = (string) $this->pick($body, [
            'status', 'state', 'consent_status', 'data.status',
        ], $result['ok'] ? 'pending' : 'failed');

        if (! $result['ok']) {
            return new PspPaymentResponse(
                status: 'failed',
                providerReference: $providerReference !== '' ? $providerReference : null,
                redirectUrl: is_string($redirectUrl) ? $redirectUrl : null,
                raw: $result,
                failureCode: (string) $this->pick($body, ['error_code', 'code', 'error.code'], 'HTTP_'.$result['status']),
                failureMessage: (string) $this->pick($body, ['error_message', 'message', 'error.message'], 'AfrPay open banking payment failed.')
            );
        }

        return new PspPaymentResponse(
            status: $this->mapProviderStatus($providerStatus),
            providerReference: $providerReference !== '' ? $providerReference : null,
            redirectUrl: is_string($redirectUrl) ? $redirectUrl : null,
            raw: $result
        );
    }

    public function getPaymentStatus(string $providerReference): PspPaymentResponse
    {
        $result = $this->request(
            'GET',
            (string) ($this->config['status_path'] ?? '/v1/open-banking/payments/{reference}'),
            [],
            [],
            ['reference' => $providerReference]
        );

        $body = $result['json'];
        $providerStatus = (string) $this->pick($body, [
            'status', 'state', 'consent_status', 'data.status',
        ], $result['ok'] ? 'unknown' : 'failed');

        if (! $result['ok']) {
            return new PspPaymentResponse(
                status: 'failed',
                providerReference: $providerReference,
                raw: $result,
                failureCode: (string) $this->pick($body, ['error_code', 'code'], 'HTTP_'.$result['status']),
                failureMessage: (string) $this->pick($body, ['error_message', 'message'], 'AfrPay open banking status lookup failed.')
            );
        }

        return new PspPaymentResponse(
            status: $this->mapProviderStatus($providerStatus),
            providerReference: $providerReference,
            redirectUrl: $this->pick($body, ['redirect_url', 'authorization_url', 'data.redirect_url']),
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
        ];

        $result = $this->request(
            'POST',
            (string) ($this->config['refund_path'] ?? '/v1/open-banking/refunds'),
            $payload,
            ['Idempotency-Key' => $request->idempotencyKey]
        );

        $body = $result['json'];
        $providerStatus = (string) $this->pick($body, [
            'status', 'state', 'data.status',
        ], $result['ok'] ? 'pending' : 'failed');

        if (! $result['ok']) {
            return new PspRefundResponse(
                status: 'failed',
                providerReference: $request->providerReference,
                raw: $result,
                failureCode: (string) $this->pick($body, ['error_code', 'code'], 'HTTP_'.$result['status']),
                failureMessage: (string) $this->pick($body, ['error_message', 'message'], 'AfrPay open banking refund failed.')
            );
        }

        return new PspRefundResponse(
            status: $this->mapProviderStatus($providerStatus),
            providerReference: (string) $this->pick($body, ['refund_id', 'id', 'data.id'], $request->providerReference),
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
            status: $this->mapProviderStatus((string) $this->pick($payload, ['status', 'state', 'consent_status', 'data.status'], 'unknown')),
            providerReference: $this->pick($payload, ['payment_id', 'transaction_id', 'consent_id', 'id', 'data.payment_id']),
            merchantReference: $this->pick($payload, ['merchant_reference', 'merchant_ref', 'data.merchant_reference']),
            raw: $payload
        );
    }
}
