<?php

namespace App\Services\Psp\Media;

interface MediaImpressionRepositoryInterface
{
    public function findByAttemptAndSlot(string $paymentAttemptId, string $slot): ?array;

    public function store(array $impression): array;

    public function all(): array;
}
