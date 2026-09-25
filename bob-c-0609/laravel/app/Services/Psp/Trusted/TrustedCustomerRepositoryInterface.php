<?php

namespace App\Services\Psp\Trusted;

interface TrustedCustomerRepositoryInterface
{
    public function findByStableKey(string $stableCustomerKey): ?array;
}
