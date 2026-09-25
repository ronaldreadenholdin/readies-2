<?php

namespace App\DTO;

final class PspRefundResponse
{
    public function __construct(private array $normalized)
    {
    }

    public static function fromNormalized(array $normalized): self
    {
        return new self($normalized);
    }

    public function normalized(): array
    {
        return $this->normalized;
    }

    public function toArray(): array
    {
        return $this->normalized;
    }
}
