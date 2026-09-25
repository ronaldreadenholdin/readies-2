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

    public function getPaymentStatus(string $paymentId): PspPaymentResponse;

    public function refund(PspRefundRequest $request): PspRefundResponse;

    public function verifyWebhook(array $headers, string $payload): bool;

    public function handleWebhook(array $headers, string $payload): PspWebhookResult;
}
