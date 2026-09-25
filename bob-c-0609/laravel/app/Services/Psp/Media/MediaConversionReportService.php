<?php

namespace App\Services\Psp\Media;

final class MediaConversionReportService
{
    public function report(array $impressions): array
    {
        $rows = [];
        foreach ($impressions as $impression) {
            $key = implode('|', [
                $impression['merchant_id'] ?? 'unknown',
                $impression['slot'] ?? 'unknown',
                $impression['variant'] ?? 'none',
            ]);
            $rows[$key] ??= [
                'merchant_id' => $impression['merchant_id'] ?? 'unknown',
                'slot' => $impression['slot'] ?? 'unknown',
                'variant' => $impression['variant'] ?? 'none',
                'attempts' => 0,
                'success' => 0,
                'failed' => 0,
                'abandoned' => 0,
                'conversion_rate' => '0.00',
                'abandonment_rate' => '0.00',
            ];
            $rows[$key]['attempts']++;
            $outcome = $impression['payment_outcome'] ?? 'abandoned';
            if (isset($rows[$key][$outcome])) {
                $rows[$key][$outcome]++;
            }
        }

        foreach ($rows as &$row) {
            $attempts = max(1, (int) $row['attempts']);
            $row['conversion_rate'] = number_format(((int) $row['success'] / $attempts) * 100, 2);
            $row['abandonment_rate'] = number_format(((int) $row['abandoned'] / $attempts) * 100, 2);
        }

        return array_values($rows);
    }
}
