<?php

namespace App\DTO;

class PspWebhookResult
{
    public function __construct(
        public readonly string $eventType,
        public readonly string $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $merchantReference = null,
        public readonly array $raw = [],
    ) {
    }
}
