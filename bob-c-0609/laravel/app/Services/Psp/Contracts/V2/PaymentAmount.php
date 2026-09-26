<?php

namespace App\Services\Psp\Contracts\V2;

final class PaymentAmount
{
    public function __construct(
        public string $value,
        public string $currency,
        public int $minorUnits,
    ) {
    }

    public static function fromArray(array $amount): self
    {
        return new self(
            (string) ($amount['value'] ?? ''),
            (string) ($amount['currency'] ?? ''),
            (int) ($amount['minor_units'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'currency' => $this->currency,
            'minor_units' => $this->minorUnits,
        ];
    }
}
