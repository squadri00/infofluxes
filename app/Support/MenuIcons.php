<?php

namespace App\Support;

/** The small icons that can sit beside a category in the big menu. Simple line drawings in a 24 x 24 box, made for this site. */
class MenuIcons
{
    /** key => [label, the drawing (paths and shapes, drawn with the text colour)] */
    public const ALL = [
        'book' => ['Book', '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5z"/><path d="M8 7h7"/>'],
        'quran' => ['Open book', '<path d="M12 6c-2-1.5-5-2-8-1.5v13c3-.5 6 0 8 1.5 2-1.5 5-2 8-1.5v-13C17 4 14 4.5 12 6z"/><path d="M12 6v13"/>'],
        'scroll' => ['Scroll', '<path d="M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 0 2 2H9a2 2 0 0 1-2-2V4z"/><path d="M7 4a2 2 0 0 0-2 2v2h2"/>'],
        'history' => ['History (clock)', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'science' => ['Science (flask)', '<path d="M9 3h6M10 3v6L5 19a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2l-5-10V3"/>'],
        'globe' => ['World', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14 0 18M12 3c-3 3.5-3 14 0 18"/>'],
        'scale' => ['Law (scales)', '<path d="M12 4v16M6 20h12M5 7h14M5 7l-3 7a3 3 0 0 0 6 0L5 7zM19 7l-3 7a3 3 0 0 0 6 0l-3-7z"/>'],
        'people' => ['People', '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14a5 5 0 0 1 6 5"/>'],
        'question' => ['Question', '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>'],
        'lightbulb' => ['Idea (light bulb)', '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"/>'],
        'map' => ['Map', '<path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14"/>'],
        'heart' => ['Heart', '<path d="M12 20s-7-4.5-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.5-9 9-9 9z"/>'],
        'star' => ['Star', '<path d="M12 3l2.8 5.8 6.2.9-4.5 4.4 1 6.2L12 17.4l-5.5 2.9 1-6.2L3 9.7l6.2-.9L12 3z"/>'],
        'document' => ['Document', '<path d="M7 3h7l5 5v13H7V3zM14 3v5h5M10 13h6M10 17h6"/>'],
        'compass' => ['Compass', '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z"/>'],
        'shield' => ['Shield (defence, wars)', '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/>'],
        'coins' => ['Coin (money, trade)', '<circle cx="12" cy="12" r="9"/><path d="M12 6.5v11M9.5 9.8c0-2 5-2 5 0s-5 1.4-5 3.4 5 2 5 0"/>'],
        'home' => ['Home (family)', '<path d="M3 11l9-8 9 8M5 10v10h14V10M10 20v-6h4v6"/>'],
        'columns' => ['Columns (government)', '<path d="M3 10l9-6 9 6H3zM4 21h16M6 10v11M10 10v11M14 10v11M18 10v11"/>'],
        'flag' => ['Flag', '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>'],
        'palette' => ['Palette (art)', '<path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 1.5-2s0-2 1.5-2h2a4 4 0 0 0 4-4A9 9 0 0 0 12 3zM7.5 11h.01M10 7.5h.01M14 7.5h.01"/>'],
        'graduation' => ['Graduation cap (learning)', '<path d="M2 9l10-5 10 5-10 5L2 9zM6 11v5c3 2.5 9 2.5 12 0v-5"/>'],
        'chart' => ['Chart (modern times)', '<path d="M4 20V4M4 20h16M8 16v-4M12 16V8M16 16v-6"/>'],
        'dome' => ['Dome (house of worship)', '<path d="M4 21V11M20 21V11M7 21v-6a5 5 0 0 1 10 0v6M12 3v3M3 21h18"/>'],
    ];

    /** key => label, for a drop-down. @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn ($i) => $i[0], static::ALL);
    }

    /** The whole icon as an svg, or an empty string for a key that does not exist. */
    public static function svg(?string $key, int $size = 22): string
    {
        $icon = static::ALL[(string) $key] ?? null;
        if (! $icon) {
            return '';
        }

        return '<svg viewBox="0 0 24 24" width="'.$size.'" height="'.$size.'" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$icon[1].'</svg>';
    }
}
