<?php

namespace App\Services\Psp\Media;

final class InMemoryWaitingMediaRepository implements WaitingMediaRepositoryInterface
{
    private array $mediaById = [];
    private array $consents = [];

    public function __construct(array $media = [], array $consents = [])
    {
        foreach ($media as $row) {
            $this->mediaById[$row['media_id']] = $row;
        }
        foreach ($consents as $row) {
            $this->consents[$this->key($row['merchant_id'], $row['media_id'], $row['slot'])] = $row;
        }
    }

    public function all(): array
    {
        return array_values($this->mediaById);
    }

    public function find(string $mediaId): ?array
    {
        return $this->mediaById[$mediaId] ?? null;
    }

    public function approvedPlatformClips(): array
    {
        return array_values(array_filter($this->mediaById, static fn (array $row): bool => ($row['owner'] ?? '') === 'platform' && ($row['status'] ?? '') === 'approved'));
    }

    public function consent(string $merchantId, string $mediaId, string $slot): ?array
    {
        return $this->consents[$this->key($merchantId, $mediaId, $slot)] ?? null;
    }

    public function approve(string $merchantId, string $mediaId, string $slot, string $approvedBy): array
    {
        return $this->consents[$this->key($merchantId, $mediaId, $slot)] = [
            'merchant_id' => $merchantId,
            'media_id' => $mediaId,
            'slot' => $slot,
            'approved' => true,
            'approved_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'approved_by' => $approvedBy,
        ];
    }

    public function revoke(string $merchantId, string $mediaId, string $slot, string $approvedBy): array
    {
        return $this->consents[$this->key($merchantId, $mediaId, $slot)] = [
            'merchant_id' => $merchantId,
            'media_id' => $mediaId,
            'slot' => $slot,
            'approved' => false,
            'approved_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'approved_by' => $approvedBy,
        ];
    }

    private function key(string $merchantId, string $mediaId, string $slot): string
    {
        return "{$merchantId}|{$mediaId}|{$slot}";
    }
}
