<?php

namespace App\Services\Psp\Credentials;

final class VaultPspCredentialProvider implements PspCredentialProviderInterface
{
    public function get(string $pspCode, string $environment): ?array
    {
        // TODO: connect to the real 0609 Laravel vault on the VPS.
        // Do not read PSP secrets from env/config/monday/request payloads.
        return null;
    }

    public function status(string $pspCode, string $environment): array
    {
        return [
            'psp_code' => strtoupper($pspCode),
            'environment' => $environment,
            'status' => 'missing',
            'last_rotated_at' => null,
            'vault_entry_link' => 'TODO: link to 0609 vault entry',
        ];
    }
}
