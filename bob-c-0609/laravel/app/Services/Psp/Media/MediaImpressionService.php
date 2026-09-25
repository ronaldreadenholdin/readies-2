<?php

namespace App\Services\Psp\Media;

final class MediaImpressionService
{
    public function __construct(private MediaImpressionRepositoryInterface $impressions, private int $minimumVisibleSeconds = 2)
    {
    }

    public function recordShown(array $event): array
    {
        if ((int) ($event['visible_duration_seconds'] ?? 0) < $this->minimumVisibleSeconds) {
            return ['counted' => false, 'reason' => 'under_minimum_visibility'];
        }

        if ($this->impressions->findByAttemptAndSlot((string) $event['payment_attempt_id'], (string) $event['slot']) !== null) {
            return ['counted' => false, 'reason' => 'duplicate_attempt_slot'];
        }

        $impression = [
            'impression_id' => $event['impression_id'] ?? bin2hex(random_bytes(12)),
            'media_id' => $event['media_id'] ?? null,
            'advertiser_id' => $event['advertiser_id'] ?? null,
            'owner' => $event['owner'] ?? 'platform',
            'merchant_id' => $event['merchant_id'],
            'slot' => $event['slot'],
            'payment_attempt_id' => $event['payment_attempt_id'],
            'merchant_reference' => $event['merchant_reference'],
            'shown_at' => $event['shown_at'] ?? gmdate('Y-m-d\TH:i:s\Z'),
            'visible_duration_seconds' => (int) $event['visible_duration_seconds'],
            'completed' => (bool) ($event['completed'] ?? false),
            'clicked' => (bool) ($event['clicked'] ?? false),
            'payment_outcome' => $event['payment_outcome'] ?? null,
            'variant' => $event['variant'] ?? ($event['media_id'] ?? 'none'),
        ];

        return ['counted' => true, 'impression' => $this->impressions->store($impression)];
    }
}
