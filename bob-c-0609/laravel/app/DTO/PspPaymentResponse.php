<?php

namespace App\DTO;

final class PspPaymentResponse
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

    public function status(): string
    {
        return (string) ($this->normalized['status'] ?? 'unknown');
    }

    public function cascadeEligible(): bool
    {
        return (bool) ($this->normalized['metadata']['cascade_eligible'] ?? false);
    }
}
