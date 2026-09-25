<?php

namespace App\Services\Psp\Media;

final class WaitingMediaResolver
{
    public const DEFAULT_MEDIA_ID = 'pigeon_card_delivery';

    public function __construct(private WaitingMediaRepositoryInterface $media)
    {
    }

    public function resolve(string $merchantId, string $slot, ?string $requestedMediaId): array
    {
        $media = $requestedMediaId === null ? null : $this->media->find($requestedMediaId);
        $consent = $media === null ? null : $this->media->consent($merchantId, $media['media_id'], $slot);
        $allowed = $media !== null
            && ($media['status'] ?? null) === 'approved'
            && ($consent['approved'] ?? false) === true;

        if (! $allowed) {
            $media = $this->media->find(self::DEFAULT_MEDIA_ID);
        }

        return [
            'media' => $media,
            'audit' => [
                'media_id_shown' => $media['media_id'] ?? null,
                'requested_media_id' => $requestedMediaId,
                'merchant_id' => $merchantId,
                'slot' => $slot,
                'fallback_used' => ! $allowed,
            ],
        ];
    }
}
