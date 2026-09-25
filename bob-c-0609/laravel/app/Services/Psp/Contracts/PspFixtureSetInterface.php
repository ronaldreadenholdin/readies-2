<?php

namespace App\Services\Psp\Contracts;

use App\DTO\PspPaymentRequest;
use App\DTO\PspRefundRequest;

interface PspFixtureSetInterface
{
    public function paymentRequest(): PspPaymentRequest;

    public function refundRequest(): PspRefundRequest;

    public function webhookPayload(): string;

    public function webhookHeaders(): array;

    public function golden(string $connection): array;

    public function preflightChecks(): array;

    public function commercialProfile(): array;
}
