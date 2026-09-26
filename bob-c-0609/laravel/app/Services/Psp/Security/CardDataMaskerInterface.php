<?php

namespace App\Services\Psp\Security;

interface CardDataMaskerInterface
{
    /**
     * Return an equivalent value with PAN, CVV/CVC, expiry, and track data masked.
     *
     * Implementations must be idempotent, provider-agnostic, safe for nested
     * arrays and objects, safe for invalid UTF-8, and must never throw.
     */
    public function mask(mixed $value): mixed;
}
