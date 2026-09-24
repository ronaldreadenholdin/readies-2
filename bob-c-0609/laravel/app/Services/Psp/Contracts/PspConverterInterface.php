<?php

namespace App\Services\Psp\Contracts;

use App\DTO\PspPaymentRequest;
use App\DTO\PspRefundRequest;

interface PspConverterInterface
{
    public function pspCode(): string;

    /**
     * Dotted internal field paths that must exist before this PSP is attempted.
     *
     * @return list<string>
     */
    public function requiredFields(): array;

    /**
     * Dotted internal field paths mapped into create-payment PSP fields.
     *
     * @return array<string, string>
     */
    public function createPayloadFieldMap(): array;

    public function toCreatePaymentPayload(PspPaymentRequest $request): array;

    public function toRefundPayload(PspRefundRequest $request): array;

    public function normalizeCreatePaymentResponse(array $payload, PspPaymentRequest $request): array;

    public function normalizeStatusResponse(array $payload): array;

    public function normalizeRefundResponse(array $payload, PspRefundRequest $request): array;

    public function normalizeWebhookEvent(array $payload, bool $signatureVerified): array;
}
