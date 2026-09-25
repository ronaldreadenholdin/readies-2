<?php

namespace App\Services\Psp\Marketing;

final class MarketingResultsService
{
    public function aggregate(array $impressions, string $from, string $to, string $bucket = 'day'): array
    {
        $rows = [];
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        foreach ($impressions as $row) {
            $shownAt = strtotime((string) $row['shown_at']);
            if ($shownAt < $fromTs || $shownAt > $toTs) {
                continue;
            }
            $bucketKey = $bucket === 'week' ? gmdate('o-\WW', $shownAt) : gmdate('Y-m-d', $shownAt);
            $key = implode('|', [
                $bucketKey,
                $row['media_id'] ?? 'none',
                $row['slot'] ?? 'unknown',
                $row['merchant_id'] ?? 'unknown',
                $row['site'] ?? 'unknown',
            ]);
            $rows[$key] ??= [
                'bucket' => $bucketKey,
                'media_id' => $row['media_id'] ?? 'none',
                'slot' => $row['slot'] ?? 'unknown',
                'merchant_id' => $row['merchant_id'] ?? 'unknown',
                'site' => $row['site'] ?? 'unknown',
                'impressions' => 0,
                'completed_views' => 0,
                'clicks' => 0,
                'success' => 0,
                'control_success' => 0,
            ];
            $rows[$key]['impressions']++;
            $rows[$key]['completed_views'] += (int) ($row['completed'] ?? false);
            $rows[$key]['clicks'] += (int) ($row['clicked'] ?? false);
            $rows[$key]['success'] += (($row['payment_outcome'] ?? null) === 'success' && ($row['variant'] ?? 'none') !== 'none') ? 1 : 0;
            $rows[$key]['control_success'] += (($row['payment_outcome'] ?? null) === 'success' && ($row['variant'] ?? 'none') === 'none') ? 1 : 0;
        }

        return array_values($rows);
    }

    public function now(array $impressions, string $now): array
    {
        return [
            'today' => $this->aggregate($impressions, gmdate('Y-m-d 00:00:00', strtotime($now)), $now),
            'last_24_hours' => $this->aggregate($impressions, gmdate('Y-m-d H:i:s', strtotime($now) - 86400), $now),
        ];
    }
}
