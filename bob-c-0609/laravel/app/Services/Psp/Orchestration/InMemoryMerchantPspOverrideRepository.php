<?php

namespace App\Services\Psp\Orchestration;

final class InMemoryMerchantPspOverrideRepository
{
    private array $rows = [];

    public function append(string $merchantId, string $connectionCode, int $fromPosition, int $toPosition, string $reason, string $overriddenBy): array
    {
        $row = [
            'merchant_id' => $merchantId,
            'connection_code' => $connectionCode,
            'from_position' => $fromPosition,
            'to_position' => $toPosition,
            'reason' => $reason,
            'overridden_by' => $overriddenBy,
            'overridden_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        $this->rows[] = $row;

        return $row;
    }

    public function all(): array
    {
        return $this->rows;
    }
}
