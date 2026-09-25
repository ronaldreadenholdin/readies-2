<?php

namespace App\Services\Psp\Standards;

final class ProviderConnectionRegistry
{
    public function __construct(private array $connections)
    {
    }

    public static function fromJsonFile(string $path): self
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        return new self(is_array($decoded) ? $decoded : []);
    }

    public function forProvider(string $providerCode): ?array
    {
        foreach ($this->connections as $connection) {
            if (($connection['provider_code'] ?? null) === strtoupper($providerCode)) {
                return $connection;
            }
        }

        return null;
    }

    public function all(): array
    {
        return $this->connections;
    }
}
