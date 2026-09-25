<?php

namespace App\Services\Psp\Trusted;

final class InMemoryTrustedCustomerRepository implements TrustedCustomerRepositoryInterface
{
    public function __construct(private array $customers = [])
    {
    }

    public function put(string $stableCustomerKey, array $customer): void
    {
        $this->customers[$stableCustomerKey] = $customer;
    }

    public function findByStableKey(string $stableCustomerKey): ?array
    {
        return $this->customers[$stableCustomerKey] ?? null;
    }
}
