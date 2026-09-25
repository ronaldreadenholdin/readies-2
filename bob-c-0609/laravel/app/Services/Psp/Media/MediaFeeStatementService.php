<?php

namespace App\Services\Psp\Media;

final class MediaFeeStatementService
{
    public function statement(array $impressions, array $rates, string $groupBy): array
    {
        $rows = [];
        foreach ($impressions as $impression) {
            $key = (string) ($impression[$groupBy] ?? 'unknown');
            $rate = $rates[$impression['media_id']] ?? null;
            $rows[$key] ??= [
                $groupBy => $key,
                'impressions' => 0,
                'billable_events' => 0,
                'currency' => $rate['currency'] ?? 'EUR',
                'fee_owed' => '0.0000',
                'merchant_share' => '0.0000',
            ];
            $rows[$key]['impressions']++;
            if ($rate === null || ! $this->isBillable($impression, $rate['billable_event'])) {
                continue;
            }

            $rows[$key]['billable_events']++;
            $fee = (float) $rows[$key]['fee_owed'] + (float) $rate['amount'];
            $share = $fee * ((float) ($rate['merchant_revenue_share_percent'] ?? 0) / 100);
            $rows[$key]['fee_owed'] = number_format($fee, 4, '.', '');
            $rows[$key]['merchant_share'] = number_format($share, 4, '.', '');
        }

        return array_values($rows);
    }

    private function isBillable(array $impression, string $event): bool
    {
        return match ($event) {
            'impression' => true,
            'completed_view' => (bool) ($impression['completed'] ?? false),
            'click' => (bool) ($impression['clicked'] ?? false),
            default => false,
        };
    }
}
