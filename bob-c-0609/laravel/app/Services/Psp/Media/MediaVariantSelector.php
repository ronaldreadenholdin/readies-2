<?php

namespace App\Services\Psp\Media;

final class MediaVariantSelector
{
    public function __construct(private int $controlGroupPercent = 0)
    {
    }

    public function choose(string $paymentAttemptId, ?string $mediaId): array
    {
        $bucket = hexdec(substr(hash('sha256', $paymentAttemptId), 0, 4)) % 100;
        if ($bucket < $this->controlGroupPercent) {
            return [
                'variant' => 'none',
                'media_id' => null,
                'control_group' => true,
            ];
        }

        return [
            'variant' => $mediaId === null ? 'default_animation' : $mediaId,
            'media_id' => $mediaId,
            'control_group' => false,
        ];
    }
}
