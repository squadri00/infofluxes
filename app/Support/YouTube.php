<?php

namespace App\Support;

/** Works out the video code from any normal YouTube address. */
class YouTube
{
    /** @return string|null the 11-character video code, or null when the address is not a YouTube video */
    public static function id(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $patterns = [
            '~^https?://(?:www\.|m\.)?youtube\.com/watch\?(?:[^#]*&)?v=([A-Za-z0-9_-]{11})(?:[&#].*)?$~i',
            '~^https?://youtu\.be/([A-Za-z0-9_-]{11})(?:[?&#].*)?$~i',
            '~^https?://(?:www\.)?youtube(?:-nocookie)?\.com/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /** The address a visitor's page uses to show the video (the privacy-friendly YouTube address). */
    public static function embedUrl(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$id;
    }
}
