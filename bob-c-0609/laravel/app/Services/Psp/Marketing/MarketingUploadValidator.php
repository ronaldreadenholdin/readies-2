<?php

namespace App\Services\Psp\Marketing;

final class MarketingUploadValidator
{
    private const MAX_BYTES = 25_000_000;
    private const MIME_TYPES = [
        'video_clip' => ['video/mp4', 'video/webm'],
        'banner_image' => ['image/jpeg', 'image/png', 'image/webp'],
        'advert' => ['image/jpeg', 'image/png', 'image/webp', 'text/html'],
    ];

    public function validate(string $type, string $mimeType, int $fileSize): array
    {
        $errors = [];
        if (! in_array($mimeType, self::MIME_TYPES[$type] ?? [], true)) {
            $errors[] = 'invalid_mime_type';
        }
        if ($fileSize > self::MAX_BYTES) {
            $errors[] = 'file_too_large';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'store_outside_web_root' => true,
        ];
    }
}
