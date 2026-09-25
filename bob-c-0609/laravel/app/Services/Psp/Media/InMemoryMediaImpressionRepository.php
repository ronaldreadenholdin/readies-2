<?php

namespace App\Services\Psp\Media;

final class InMemoryMediaImpressionRepository implements MediaImpressionRepositoryInterface
{
    private array $impressions = [];

    public function findByAttemptAndSlot(string $paymentAttemptId, string $slot): ?array
    {
        return $this->impressions[$paymentAttemptId . '|' . $slot] ?? null;
    }

    public function store(array $impression): array
    {
        $this->impressions[$impression['payment_attempt_id'] . '|' . $impression['slot']] = $impression;

        return $impression;
    }

    public function all(): array
    {
        return array_values($this->impressions);
    }
}
