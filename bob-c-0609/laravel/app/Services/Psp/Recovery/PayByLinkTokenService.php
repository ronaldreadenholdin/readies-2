<?php

namespace App\Services\Psp\Recovery;

final class PayByLinkTokenService
{
    private array $tokens = [];

    public function create(string $merchantReference, string $pspCode, int $ttlSeconds = 3600): array
    {
        $token = bin2hex(random_bytes(24));
        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + $ttlSeconds);
        $this->tokens[$token] = [
            'merchant_reference' => $merchantReference,
            'psp_code' => $pspCode,
            'expires_at' => $expiresAt,
            'used' => false,
        ];

        return ['token' => $token] + $this->tokens[$token];
    }

    public function markUsed(string $token): bool
    {
        if (! isset($this->tokens[$token]) || $this->tokens[$token]['used']) {
            return false;
        }

        $this->tokens[$token]['used'] = true;

        return true;
    }

    public function issued(): array
    {
        return $this->tokens;
    }
}
