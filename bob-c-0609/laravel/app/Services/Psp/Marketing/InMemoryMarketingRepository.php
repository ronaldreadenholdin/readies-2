<?php

namespace App\Services\Psp\Marketing;

final class InMemoryMarketingRepository
{
    private array $assets = [];
    private array $placements = [];

    public function __construct(array $assets = [], array $placements = [])
    {
        foreach ($assets as $asset) {
            $this->assets[$asset['asset_id']] = $asset;
        }
        $this->placements = array_values($placements);
    }

    public function assets(): array
    {
        return array_values($this->assets);
    }

    public function findAsset(string $assetId): ?array
    {
        return $this->assets[$assetId] ?? null;
    }

    public function appendPlacement(array $placement): array
    {
        $placement['placement_id'] ??= bin2hex(random_bytes(10));
        $this->placements[] = $placement;

        return $placement;
    }

    public function placements(): array
    {
        return $this->placements;
    }
}
