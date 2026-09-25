<?php

namespace App\Services\Psp\Orchestration;

final class MerchantPspOrderService
{
    public function suggest(array $connections): array
    {
        usort($connections, function (array $a, array $b): int {
            $aEligible = (float) ($a['score_percent'] ?? 0) === 100.0 && ($a['eligible'] ?? false);
            $bEligible = (float) ($b['score_percent'] ?? 0) === 100.0 && ($b['eligible'] ?? false);
            if ($aEligible !== $bEligible) {
                return $aEligible ? -1 : 1;
            }

            $aCost = $this->cost($a);
            $bCost = $this->cost($b);
            if ($aCost === $bCost) {
                return strcmp((string) $a['connection_code'], (string) $b['connection_code']);
            }

            return $aCost <=> $bCost;
        });

        foreach ($connections as $index => &$connection) {
            $connection['suggested_position'] = $index + 1;
            $connection['actual_position'] = $connection['override_position'] ?? $connection['suggested_position'];
            $connection['live_position_allowed'] = (float) ($connection['score_percent'] ?? 0) === 100.0 && ($connection['eligible'] ?? false);
            if (! $connection['live_position_allowed']) {
                $connection['status'] = 'planned';
            }
        }

        return $connections;
    }

    public function automaticHops(array $orderedConnections, int $maxAttempts = 3): array
    {
        return array_slice(array_values(array_filter($orderedConnections, static fn (array $row): bool => (bool) ($row['live_position_allowed'] ?? false))), 0, $maxAttempts);
    }

    private function cost(array $connection): float
    {
        return (float) ($connection['mdr_percent'] ?? 999) + ((float) ($connection['mdr_fixed'] ?? 999) / 100);
    }
}
