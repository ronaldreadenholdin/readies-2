<?php

namespace App\Services\Psp\Standards;

final class AdapterStandardRegistry
{
    public function __construct(private array $standards)
    {
    }

    public static function fromJsonFile(string $path): self
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        return new self(is_array($decoded) ? $decoded : []);
    }

    public function all(): array
    {
        return $this->standards;
    }

    public function get(string $adapterNumber): ?array
    {
        foreach ($this->standards as $standard) {
            if (($standard['adapter_number'] ?? null) === $adapterNumber) {
                return $standard;
            }
        }

        return null;
    }
}
