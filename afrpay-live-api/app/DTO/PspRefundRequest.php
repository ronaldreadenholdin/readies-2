<?php

namespace App\DTO;

class PspRefundRequest
{
    public function __construct(
        public readonly string $providerReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $idempotencyKey,
        public readonly array $metadata = [],
    ) {
    }
}
