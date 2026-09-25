<?php

namespace App\Services\Psp\Orchestration;

final class MerchantPspOrderService
{
    public const TRIAL_NEW = 'New';
    public const TRIAL_ON_TRIAL = 'On trial';
    public const TRIAL_PROVEN = 'Proven';

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

    public function disagreements(array $orderedConnections): array
    {
        return array_values(array_filter(array_map(static function (array $row): ?array {
            if (($row['actual_position'] ?? null) === ($row['suggested_position'] ?? null)) {
                return null;
            }
            if (($row['trial_state'] ?? null) === self::TRIAL_ON_TRIAL && ($row['actual_position'] ?? 999) < ($row['suggested_position'] ?? 999)) {
                return null;
            }

            return [
                'merchant_id' => $row['merchant_id'] ?? null,
                'connection_code' => $row['connection_code'] ?? null,
                'suggested_position' => $row['suggested_position'] ?? null,
                'actual_position' => $row['actual_position'] ?? null,
                'suggested_reason' => $row['suggested_reason'] ?? 'System/CODA suggestion from eligibility, cost, and conformance.',
                'actual_reason' => $row['override_reason'] ?? 'Orchestration owner override.',
                'status' => 'Needs Gerardus discussion',
            ];
        }, $orderedConnections)));
    }

    public function trialRows(array $orderedConnections, array $config = []): array
    {
        $rows = [];
        foreach ($orderedConnections as $row) {
            if (($row['trial_state'] ?? null) !== self::TRIAL_ON_TRIAL) {
                continue;
            }

            $rows[] = [
                'merchant_id' => $row['merchant_id'] ?? null,
                'connection_code' => $row['connection_code'] ?? null,
                'trial_state' => self::TRIAL_ON_TRIAL,
                'trial_position' => $row['actual_position'] ?? null,
                'suggested_position' => $row['suggested_position'] ?? null,
                'set_by' => $row['trial_set_by'] ?? 'TADDY',
                'set_at' => $row['trial_set_at'] ?? null,
                'why' => $row['trial_reason'] ?? null,
                'transactions' => (int) ($row['trial_transactions'] ?? 0),
                'days_elapsed' => (int) ($row['trial_days_elapsed'] ?? 0),
                'trial_min_transactions' => $config['trial_min_transactions'] ?? null,
                'trial_max_days' => $config['trial_max_days'] ?? null,
                'message' => $this->trialMessage($row, $config),
                'live_position_allowed' => (bool) ($row['live_position_allowed'] ?? false),
            ];
        }

        return $rows;
    }

    public function trialEnded(array $row, array $config = []): bool
    {
        $minTransactions = $config['trial_min_transactions'] ?? null;
        $maxDays = $config['trial_max_days'] ?? null;
        if ($minTransactions === null && $maxDays === null) {
            return false;
        }

        return ($minTransactions !== null && (int) ($row['trial_transactions'] ?? 0) >= (int) $minTransactions)
            || ($maxDays !== null && (int) ($row['trial_days_elapsed'] ?? 0) >= (int) $maxDays);
    }

    private function trialMessage(array $row, array $config): string
    {
        if (($config['trial_min_transactions'] ?? null) === null && ($config['trial_max_days'] ?? null) === null) {
            return 'Trial length not set, needs Gerardus';
        }
        if ($this->trialEnded($row, $config)) {
            return 'Trial ended; compare actual position against stats-based suggestion';
        }

        return 'Trial in progress';
    }

    private function cost(array $connection): float
    {
        return (float) ($connection['mdr_percent'] ?? 999) + ((float) ($connection['mdr_fixed'] ?? 999) / 100);
    }
}
