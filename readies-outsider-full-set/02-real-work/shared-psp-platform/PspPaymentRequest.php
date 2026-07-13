<?php

namespace App\DTO;

class PspPaymentRequest
{
    public function __construct(
        public readonly string $merchantReference,
        public readonly string $customerReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $successUrl,
        public readonly string $failureUrl,
        public readonly string $idempotencyKey,
        public readonly array $customer = [],
        public readonly array $metadata = [],
    ) {
    }
}
