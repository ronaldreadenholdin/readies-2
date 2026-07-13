<?php

namespace App\Contracts;

use App\DTO\PspPaymentRequest;
use App\DTO\PspPaymentResponse;
use App\DTO\PspRefundRequest;
use App\DTO\PspRefundResponse;
use App\DTO\PspWebhookResult;

interface PspAdaptorInterface
{
    public function code(): string;

    public function connectionType(): string;

    public function createPayment(PspPaymentRequest $request): PspPaymentResponse;

    public function getPaymentStatus(string $providerReference): PspPaymentResponse;

    public function refund(PspRefundRequest $request): PspRefundResponse;

    public function verifyWebhook(string $rawPayload, array $headers): bool;

    public function handleWebhook(string $rawPayload, array $headers): PspWebhookResult;
}
