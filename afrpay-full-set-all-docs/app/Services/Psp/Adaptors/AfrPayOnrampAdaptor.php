<?php

namespace App\Services\Psp\Adaptors;

use App\DTO\PspPaymentRequest;
use App\DTO\PspPaymentResponse;
use App\DTO\PspRefundRequest;
use App\DTO\PspRefundResponse;
use App\DTO\PspWebhookResult;

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
                'currency' => $request->currency,
            ],
            'target_asset' => $request->metadata['target_asset'] ?? 'USDT',
            'target_wallet_reference' => $request->metadata['exchange_wallet_reference'] ?? null,
            'success_url' => $request->successUrl,
            'failure_url' => $request->failureUrl,
            'metadata' => array_merge($request->metadata, [
                'readies_flow' => 'card_to_usdt_or_usdc_to_readies',
                'readies_rate_eur' => '0.10',
            ]),
        ];

        // TODO: replace path and response mapping when AfrPay API docs are supplied.
        return new PspPaymentResponse(
            status: 'requires_provider_api_mapping',
            raw: [
                'method' => 'POST',
                'url' => $this->endpoint($this->config['create_path'] ?? '/payments'),
                'headers' => $this->headers(['Idempotency-Key' => $request->idempotencyKey]),
                'payload' => $payload,
            ],
            failureCode: 'API_DOCS_REQUIRED',
            failureMessage: 'AfrPay Onramp API create-payment path and response mapping are not documented yet.'
        );
    }

    public function getPaymentStatus(string $providerReference): PspPaymentResponse
    {
        return new PspPaymentResponse(
            status: 'requires_provider_api_mapping',
            providerReference: $providerReference,
            raw: [
                'method' => 'GET',
                'url' => $this->endpoint(($this->config['status_path'] ?? '/payments/{reference}')),
            ],
            failureCode: 'API_DOCS_REQUIRED',
            failureMessage: 'AfrPay Onramp status endpoint is not documented yet.'
        );
    }

    public function refund(PspRefundRequest $request): PspRefundResponse
    {
        return new PspRefundResponse(
            status: 'requires_provider_api_mapping',
            providerReference: $request->providerReference,
            raw: [
                'method' => 'POST',
                'url' => $this->endpoint($this->config['refund_path'] ?? '/refunds'),
                'headers' => $this->headers(['Idempotency-Key' => $request->idempotencyKey]),
            ],
            failureCode: 'API_DOCS_REQUIRED',
            failureMessage: 'AfrPay Onramp refund/reversal endpoint is not documented yet.'
        );
    }

    public function handleWebhook(string $rawPayload, array $headers): PspWebhookResult
    {
        if (! $this->verifyWebhook($rawPayload, $headers)) {
            return new PspWebhookResult('signature_failed', 'rejected', raw: ['payload' => $rawPayload]);
        }

        $payload = json_decode($rawPayload, true) ?: [];

        return new PspWebhookResult(
            eventType: (string) ($payload['event'] ?? 'unknown'),
            status: $this->mapStatus((string) ($payload['status'] ?? 'unknown')),
            providerReference: $payload['transaction_id'] ?? $payload['id'] ?? null,
            merchantReference: $payload['merchant_reference'] ?? null,
            raw: $payload
        );
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'created', 'initiated' => 'created',
            'pending', 'processing' => 'pending',
            'paid', 'completed', 'success' => 'completed',
            'failed', 'declined', 'rejected' => 'failed',
            'refunded', 'reversed', 'chargeback' => 'reversed',
            default => 'unknown',
        };
    }
}
