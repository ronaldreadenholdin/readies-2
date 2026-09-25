<?php

namespace App\Services\Psp\Testing;

final class PspTestIsolationLockService
{
    private ?array $current = null;

    public function __construct(private InMemoryPspTestIsolationLog $log, private array $config)
    {
    }

    public function start(string $connectionCode, string $actor, ?string $merchantId = null, string $reason = 'start test'): array
    {
        if ($this->current !== null) {
            $event = $this->log->append('refused_second_test', $connectionCode, $merchantId, $this->config['test_site'], $actor, 'Another PSP is already under test.', ['current' => $this->current]);

            return ['ok' => false, 'event' => $event, 'current' => $this->current];
        }

        $this->current = [
            'connection_code' => $connectionCode,
            'merchant_id' => $merchantId,
            'test_site' => $this->config['test_site'],
            'started_by' => $actor,
            'started_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        $event = $this->log->append('started', $connectionCode, $merchantId, $this->config['test_site'], $actor, $reason);

        return ['ok' => true, 'event' => $event, 'current' => $this->current];
    }

    public function abort(string $actor, string $reason): array
    {
        $connection = $this->current['connection_code'] ?? 'none';
        $merchant = $this->current['merchant_id'] ?? null;
        $event = $this->log->append('aborted', $connection, $merchant, $this->config['test_site'], $actor, $reason);
        $this->current = null;

        return ['ok' => true, 'event' => $event];
    }

    public function promote(string $actor, float $scorePercent, bool $gerardusApproved, string $reason = 'promote'): array
    {
        $connection = $this->current['connection_code'] ?? 'none';
        $merchant = $this->current['merchant_id'] ?? null;
        if ($scorePercent !== 100.0) {
            $event = $this->log->append('promotion_refused', $connection, $merchant, $this->config['test_site'], $actor, 'Conformance is below 100%.', ['score_percent' => $scorePercent]);

            return ['ok' => false, 'event' => $event];
        }
        if (! $gerardusApproved) {
            $event = $this->log->append('promotion_refused', $connection, $merchant, $this->config['test_site'], $actor, 'Gerardus approval missing.', ['score_percent' => $scorePercent]);

            return ['ok' => false, 'event' => $event];
        }

        $event = $this->log->append('promoted', $connection, $merchant, $this->config['test_site'], $actor, $reason, ['score_percent' => $scorePercent, 'gerardus_approved' => true]);
        $this->current = null;

        return ['ok' => true, 'event' => $event];
    }

    public function guard(string $connectionCode, string $action, string $actor): array
    {
        if ($this->current === null) {
            return ['ok' => true];
        }
        if (($this->current['connection_code'] ?? null) === $connectionCode) {
            return ['ok' => true];
        }

        $event = $this->log->append('blocked_' . $action, $connectionCode, $this->current['merchant_id'] ?? null, $this->config['test_site'], $actor, 'Only the PSP under test may be touched while a test is active.', ['current' => $this->current]);

        return ['ok' => false, 'event' => $event];
    }

    public function current(): ?array
    {
        return $this->current;
    }
}
