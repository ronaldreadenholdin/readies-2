<?php

namespace App\Services\Psp\Media;

interface WaitingMediaRepositoryInterface
{
    public function all(): array;

    public function find(string $mediaId): ?array;

    public function approvedPlatformClips(): array;

    public function consent(string $merchantId, string $mediaId, string $slot): ?array;

    public function approve(string $merchantId, string $mediaId, string $slot, string $approvedBy): array;

    public function revoke(string $merchantId, string $mediaId, string $slot, string $approvedBy): array;
}
