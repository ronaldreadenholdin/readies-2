<?php

namespace App\Services\Psp\Credentials;

final class FakePspCredentialProvider implements PspCredentialProviderInterface
{
    public function __construct(private array $credentials = [])
    {
    }

    public function get(string $pspCode, string $environment): ?array
    {
        return $this->credentials[strtoupper($pspCode)][$environment]['values'] ?? null;
    }

    public function status(string $pspCode, string $environment): array
    {
        $row = $this->credentials[strtoupper($pspCode)][$environment] ?? null;

        return [
            'psp_code' => strtoupper($pspCode),
            'environment' => $environment,
            'status' => $row === null ? 'missing' : ($row['status'] ?? 'received'),
            'last_rotated_at' => $row['last_rotated_at'] ?? null,
            'vault_entry_link' => $row['vault_entry_link'] ?? null,
        ];
    }
}
