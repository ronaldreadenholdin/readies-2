<?php

namespace App\Services\Psp\Contracts\V2;

use App\DTO\PspPaymentRequest;
use App\DTO\PspRefundRequest;
use App\Services\Psp\Contracts\PspConverterInterface;

interface PspConverterV2Interface extends PspConverterInterface
{
    /**
     * Verify the PSP callback or status payload before conversion.
     *
     * The implementation chooses verification material from our merchant
     * account, compares signatures in constant time, fails closed, uses the
     * runtime environment for sandbox/production separation, and never logs
     * received or expected signatures.
     */
    public function verify(
        array $headers,
        string $payload,
        string $merchantAccountId,
        string $environment,
    ): bool;

    public function normalizeCreatePaymentPageResult(array $payload, PspPaymentRequest $request): PaymentPageResult;

    public function normalizeStatusPaymentEvent(array $payload, bool $authenticated): PaymentEvent;

    public function normalizeRefundPaymentEvent(array $payload, PspRefundRequest $request, bool $authenticated): PaymentEvent;

    public function normalizeWebhookPaymentEvent(array $payload, bool $signatureVerified): PaymentEvent;
}
