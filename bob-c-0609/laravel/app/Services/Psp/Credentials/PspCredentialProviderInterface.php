<?php

namespace App\Services\Psp\Credentials;

interface PspCredentialProviderInterface
{
    public function get(string $pspCode, string $environment): ?array;

    public function status(string $pspCode, string $environment): array;
}
