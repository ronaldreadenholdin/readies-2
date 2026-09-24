<?php

namespace App\Services\Psp;

use App\Contracts\PspAdaptorInterface;
use InvalidArgumentException;

final class PspAdapterRegistry
{
    /** @var array<string, PspAdaptorInterface> */
    private array $adaptors = [];

    public function register(PspAdaptorInterface $adaptor): self
    {
        $this->adaptors[$adaptor->code()] = $adaptor;

        return $this;
    }

    public function get(string $code): PspAdaptorInterface
    {
        $code = strtoupper(trim($code));
        if (! isset($this->adaptors[$code])) {
            throw new InvalidArgumentException("PSP adaptor {$code} is not registered.");
        }

        return $this->adaptors[$code];
    }

    public function has(string $code): bool
    {
        return isset($this->adaptors[strtoupper(trim($code))]);
    }

    public function eligibleForCascade(string $code, array $latestConformanceReport): bool
    {
        $summary = $latestConformanceReport['summary'][$code] ?? $latestConformanceReport['summary'][strtoupper($code)] ?? null;

        return is_array($summary)
            && (bool) ($summary['eligible_for_cascade'] ?? false)
            && (float) ($summary['score_percent'] ?? 0.0) === 100.0;
    }

    public function codes(): array
    {
        return array_keys($this->adaptors);
    }
}
