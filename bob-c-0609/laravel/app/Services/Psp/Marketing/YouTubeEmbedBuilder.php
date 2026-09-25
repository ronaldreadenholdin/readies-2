<?php

namespace App\Services\Psp\Marketing;

final class YouTubeEmbedBuilder
{
    public function build(string $url): ?array
    {
        if (! preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $matches)) {
            return null;
        }

        return [
            'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $matches[1] . '?autoplay=0&mute=1&rel=0',
            'autoplay_with_sound' => false,
            'privacy_enhanced' => true,
        ];
    }
}
