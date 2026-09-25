<?php

namespace App\Services\Psp\Trusted;

interface CardTokenVaultInterface
{
    public function tokenFor(string $stableCustomerKey, string $pspCode): ?string;
}
