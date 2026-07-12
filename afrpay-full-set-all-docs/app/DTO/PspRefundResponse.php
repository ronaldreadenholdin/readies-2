<?php

namespace App\DTO;

class PspRefundResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerReference = null,
        public readonly array $raw = [],
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
    ) {
    }
}
