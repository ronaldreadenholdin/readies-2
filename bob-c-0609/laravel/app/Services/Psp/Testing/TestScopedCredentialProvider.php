<?php

namespace App\Services\Psp\Testing;

use App\Services\Psp\Credentials\PspCredentialProviderInterface;

final class TestScopedCredentialProvider implements PspCredentialProviderInterface
{
    public function __construct(private PspCredentialProviderInterface $inner, private PspTestIsolationLockService $lock, private array $providerToConnection)
    {
    }

    public function get(string $pspCode, string $environment): ?array
    {
        $connection = $this->providerToConnection[strtoupper($pspCode)] ?? strtoupper($pspCode);
        if (! $this->lock->guard($connection, 'credential_read', 'test-harness')['ok']) {
            return null;
        }

        return $this->inner->get($pspCode, $environment);
    }

    public function status(string $pspCode, string $environment): array
    {
        return $this->inner->status($pspCode, $environment);
    }
}
