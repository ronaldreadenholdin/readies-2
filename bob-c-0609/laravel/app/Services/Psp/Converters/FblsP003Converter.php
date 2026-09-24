<?php

namespace App\Services\Psp\Converters;

use App\DTO\PspPaymentRequest;
use App\DTO\PspRefundRequest;
use App\Services\Psp\Contracts\PspConverterInterface;
use App\Services\Psp\PspNormalizedContract;

final class FblsP003Converter implements PspConverterInterface
{
    public function pspCode(): string
    {
        return 'P003';
    }

    public function requiredFields(): array
    {
        return [
            'merchant_reference',
            'amount.value',
            'amount.currency',
            'customer.email',
            'billing.postal_code',
        ];
    }

    public function createPayloadFieldMap(): array
    {
        return [
            'merchant_reference' => 'merchantRef',
            'amount.value' => 'amountCents',
            'amount.currency' => 'currency',
            'customer.email' => 'customer.email',
            'billing.postal_code' => 'customer.billingZip',
        ];
    }

    public function toCreatePaymentPayload(PspPaymentRequest $request): array
    {
        $amount = PspNormalizedContract::amountFromDecimal(
            (string) $request->get('amount.value'),
            (string) $request->get('amount.currency'),
        );

        return [
            'merchantRef' => trim((string) $request->get('merchant_reference')),
            'amountCents' => $amount->minorUnits,
            'currency' => $amount->currency,
            'customer' => [
                'email' => trim((string) $request->get('customer.email')),
                'billingZip' => trim((string) $request->get('billing.postal_code')),
            ],
        ];
    }

    public function toRefundPayload(PspRefundRequest $request): array
    {
        $amount = PspNormalizedContract::amountFromDecimal(
            (string) $request->get('amount.value'),
            (string) $request->get('amount.currency'),
        );

        return [
            'paymentId' => trim((string) $request->get('payment_id')),
            'amountCents' => $amount->minorUnits,
            'currency' => $amount->currency,
            'reason' => trim((string) $request->get('reason', 'merchant_request')),
        ];
    }

    public function normalizeCreatePaymentResponse(array $payload, PspPaymentRequest $request): array
    {
        $amount = PspNormalizedContract::amountFromMinorUnits((int) $payload['amount_cents'], (string) $payload['currency']);
        $status = $this->mapStatus((string) $payload['status']);

        return PspNormalizedContract::blank(
            $this->pspCode(),
            'create_payment',
            (string) ($payload['merchant_ref'] ?? $request->get('merchant_reference')),
            (string) $payload['id'],
            (string) ($payload['transaction_id'] ?? ''),
            $status,
            $amount->value,
            $amount->currency,
            $amount->minorUnits,
            (string) $payload['created_at'],
            (string) $payload['updated_at'],
            $this->declineFor($status, $payload),
            null,
            ['cascade_eligible' => $this->cascadeEligible($status, $payload)],
        );
    }

    public function normalizeStatusResponse(array $payload): array
    {
        $amount = PspNormalizedContract::amountFromMinorUnits((int) $payload['amount_cents'], (string) $payload['currency']);
        $status = $this->mapStatus((string) $payload['status']);

        return PspNormalizedContract::blank(
            $this->pspCode(),
            'payment_status',
            (string) $payload['merchant_ref'],
            (string) $payload['id'],
            (string) ($payload['transaction_id'] ?? ''),
            $status,
            $amount->value,
            $amount->currency,
            $amount->minorUnits,
            (string) $payload['created_at'],
            (string) $payload['updated_at'],
            $this->declineFor($status, $payload),
            null,
            ['cascade_eligible' => $this->cascadeEligible($status, $payload)],
        );
    }

    public function normalizeRefundResponse(array $payload, PspRefundRequest $request): array
    {
        $amount = PspNormalizedContract::amountFromMinorUnits((int) $payload['amount_cents'], (string) $payload['currency']);

        return PspNormalizedContract::blank(
            $this->pspCode(),
            'refund',
            (string) ($payload['merchant_ref'] ?? $request->get('merchant_reference', 'refund')),
            (string) $payload['payment_id'],
            (string) ($payload['refund_id'] ?? ''),
            'refunded',
            $amount->value,
            $amount->currency,
            $amount->minorUnits,
            (string) $payload['created_at'],
            (string) $payload['updated_at'],
            ['class' => 'none'],
            null,
            ['cascade_eligible' => false],
        );
    }

    public function normalizeWebhookEvent(array $payload, bool $signatureVerified): array
    {
        $payment = $payload['payment'] ?? [];
        $amount = PspNormalizedContract::amountFromMinorUnits((int) $payment['amount_cents'], (string) $payment['currency']);
        $status = $this->mapStatus((string) $payment['status']);

        return PspNormalizedContract::blank(
            $this->pspCode(),
            'webhook',
            (string) $payment['merchant_ref'],
            (string) $payment['id'],
            (string) ($payment['transaction_id'] ?? ''),
            $status,
            $amount->value,
            $amount->currency,
            $amount->minorUnits,
            (string) $payment['created_at'],
            (string) $payment['updated_at'],
            $this->declineFor($status, $payment),
            [
                'event_id' => trim((string) $payload['event_id']),
                'event_type' => trim((string) $payload['type']),
                'received_at' => PspNormalizedContract::toUtcTimestamp((string) $payload['received_at']),
                'signature_verified' => $signatureVerified,
            ],
            ['cascade_eligible' => $this->cascadeEligible($status, $payment)],
        );
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'created', 'requires_action', 'processing' => 'pending',
            'authorized' => 'authorized',
            'captured', 'paid' => 'captured',
            'settled' => 'settled',
            'refunded' => 'refunded',
            'cancelled', 'canceled' => 'cancelled',
            'soft_declined', 'failed_retryable' => 'failed',
            'hard_declined', 'declined' => 'declined',
            default => 'unknown',
        };
    }

    private function declineFor(string $normalizedStatus, array $payload): array
    {
        $rawStatus = strtolower((string) ($payload['status'] ?? ''));
        if (in_array($rawStatus, ['soft_declined', 'failed_retryable'], true)) {
            return [
                'class' => 'soft',
                'code' => (string) ($payload['decline_code'] ?? 'SOFT_DECLINE'),
                'message' => (string) ($payload['decline_message'] ?? 'Retryable PSP decline.'),
                'cascade_reason' => 'soft_decline',
            ];
        }
        if (in_array($rawStatus, ['hard_declined', 'declined'], true)) {
            return [
                'class' => 'hard',
                'code' => (string) ($payload['decline_code'] ?? 'HARD_DECLINE'),
                'message' => (string) ($payload['decline_message'] ?? 'Non-retryable PSP decline.'),
                'cascade_reason' => null,
            ];
        }
        if ($normalizedStatus === 'unknown') {
            return [
                'class' => 'soft',
                'code' => 'UNKNOWN_STATUS',
                'message' => 'Unknown PSP status requires sandbox review.',
                'cascade_reason' => 'unknown_status',
            ];
        }

        return ['class' => 'none'];
    }

    private function cascadeEligible(string $normalizedStatus, array $payload): bool
    {
        $decline = $this->declineFor($normalizedStatus, $payload);

        return ($decline['class'] ?? 'none') !== 'hard';
    }
}
