<?php

namespace App\Services\Psp\Testing;

final class InMemoryPspTestIsolationLog
{
    private array $events = [];

    public function append(string $eventType, string $connectionCode, ?string $merchantId, string $testSite, string $actor, ?string $reason = null, array $metadata = []): array
    {
        $event = [
            'event_type' => $eventType,
            'connection_code' => $connectionCode,
            'merchant_id' => $merchantId,
            'test_site' => $testSite,
            'actor' => $actor,
            'reason' => $reason,
            'metadata' => $metadata,
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        $this->events[] = $event;

        return $event;
    }

    public function all(): array
    {
        return $this->events;
    }
}
