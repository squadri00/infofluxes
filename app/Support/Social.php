<?php

namespace App\Support;

use App\Models\SocialLink;
use Illuminate\Support\Collection;

/**
 * The social network buttons. Each platform has a name, its brand colour and a small white drawing.
 * (The drawings are simple shapes made for this site, in the style of each network.)
 */
class Social
{
    /** key => [name, colour (any CSS background), drawing inside a 24 x 24 box] */
    public const PLATFORMS = [
        'facebook' => ['Facebook', '#1877F2', '<path fill="#fff" d="M13.4 20.5v-6.9h2.3l.4-2.7h-2.7V9.2c0-.8.3-1.3 1.4-1.3h1.4V5.5c-.3 0-1.1-.1-2.1-.1-2.1 0-3.500 1.300-3.500 3.600v2h-2.300v2.700h2.300v6.900h2.800z"/>'],
        'instagram' => ['Instagram', 'radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285aeb 90%)', '<rect x="5" y="5" width="14" height="14" rx="4.2" fill="none" stroke="#fff" stroke-width="1.8"/><circle cx="12" cy="12" r="3.3" fill="none" stroke="#fff" stroke-width="1.8"/><circle cx="16.4" cy="7.7" r="1.1" fill="#fff"/>'],
        'x' => ['X (Twitter)', '#111111', '<path d="M6.500 6l11 12M17.500 6l-11 12" stroke="#fff" stroke-width="2.200" stroke-linecap="round"/>'],
        'youtube' => ['YouTube', '#FF0000', '<rect x="4.500" y="7" width="15" height="10" rx="3" fill="#fff"/><path d="M10.500 9.600v4.800l4.200-2.400z" fill="currentColor"/>'],
        'twitch' => ['Twitch', '#9146FF', '<path d="M6 4l-1.500 3.500V17H8v3h2.500l3-3h3.200l3.300-3.300V4H6z" fill="#fff"/><path d="M10.200 8v4M14.200 8v4" stroke="currentColor" stroke-width="1.600" stroke-linecap="round"/>'],
        'tiktok' => ['TikTok', '#010101', '<path d="M13.800 5v9.300a3 3 0 1 1-2.300-2.900" fill="none" stroke="#fff" stroke-width="2.100" stroke-linecap="round"/><path d="M13.800 5c.4 2.300 1.900 3.700 4 3.800" fill="none" stroke="#fff" stroke-width="2.100" stroke-linecap="round"/>'],
        'linkedin' => ['LinkedIn', '#0A66C2', '<path d="M7.300 10v7M7.300 7.100v.1" stroke="#fff" stroke-width="2.200" stroke-linecap="round"/><path d="M11 17v-7M11 12.800c0-1.800 1.200-2.900 2.700-2.900 1.600 0 2.600 1 2.600 3V17" fill="none" stroke="#fff" stroke-width="2.200" stroke-linecap="round"/>'],
        'telegram' => ['Telegram', '#26A5E4', '<path d="M19.200 6.200L5.300 11.600c-.7.300-.7.700-.1.900l3.400 1.100 1.300 4c.2.500.3.700.7.700.4 0 .6-.2.900-.5l1.700-1.700 3.500 2.600c.6.400 1.100.2 1.300-.6l2.300-10.800c.3-1-.4-1.600-1.100-1.100z" fill="#fff"/>'],
        'whatsapp' => ['WhatsApp', '#25D366', '<path d="M12 4.500a7.500 7.500 0 0 0-6.400 11.400L4.600 19.400l3.600-.9A7.500 7.500 0 1 0 12 4.500z" fill="none" stroke="#fff" stroke-width="1.800" stroke-linejoin="round"/><path d="M9.300 9c.2 2.700 2.100 4.600 4.800 4.900l.9-1.300-1.800-.8-.7.700c-.8-.3-1.600-1.100-1.900-1.900l.7-.7-.8-1.800z" fill="#fff"/>'],
        'reddit' => ['Reddit', '#FF4500', '<ellipse cx="12" cy="13.500" rx="6.500" ry="4.500" fill="#fff"/><circle cx="9.500" cy="13" r="1.100" fill="currentColor"/><circle cx="14.500" cy="13" r="1.100" fill="currentColor"/><path d="M9.800 15.600c1.300.9 3.100.9 4.400 0" fill="none" stroke="currentColor" stroke-width="1.100" stroke-linecap="round"/><circle cx="17.800" cy="9.800" r="1.500" fill="#fff"/><circle cx="6.200" cy="9.800" r="1.500" fill="#fff"/><path d="M12 9V6l3 .8" fill="none" stroke="#fff" stroke-width="1.200"/>'],
        'pinterest' => ['Pinterest', '#E60023', '<text x="12" y="17.200" text-anchor="middle" font-family="Georgia, serif" font-weight="700" font-size="14" fill="#fff">P</text>'],
        'discord' => ['Discord', '#5865F2', '<path d="M7 8c1.500-.9 3-1.200 5-1.200S15.500 7.100 17 8c1.200 2.200 1.800 4.400 1.700 7-1 .8-2 1.300-3 1.600l-.7-1.100c-.5.100-1.200.2-3 .2s-2.500-.1-3-.2L8.300 16.600c-1-.3-2-.8-3-1.600C5.200 12.400 5.800 10.200 7 8z" fill="#fff"/><circle cx="9.800" cy="12.300" r="1.200" fill="currentColor"/><circle cx="14.200" cy="12.300" r="1.200" fill="currentColor"/>'],
        'rss' => ['RSS feed', '#F26522', '<circle cx="7.500" cy="16.500" r="1.800" fill="#fff"/><path d="M5.700 11a7.300 7.300 0 0 1 7.300 7.300M5.700 6.200A12.100 12.100 0 0 1 17.800 18.300" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/>'],
        'email' => ['Email', '#6B7280', '<rect x="5" y="7" width="14" height="10" rx="2" fill="none" stroke="#fff" stroke-width="1.800"/><path d="M5.800 8.500l6.200 4.800 6.200-4.800" fill="none" stroke="#fff" stroke-width="1.800" stroke-linejoin="round"/>'],
        'website' => ['Website', '#4B5563', '<circle cx="12" cy="12" r="7" fill="none" stroke="#fff" stroke-width="1.800"/><path d="M5 12h14M12 5c2.300 2 2.300 12 0 14M12 5c-2.300 2-2.300 12 0 14" fill="none" stroke="#fff" stroke-width="1.500"/>'],
    ];

    public static function options(): array
    {
        return array_map(fn ($p) => $p[0], self::PLATFORMS);
    }

    /** The active links in order. */
    public static function links(): Collection
    {
        try {
            return SocialLink::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->filter(fn ($l) => static::safeUrl($l->url) !== null)
                ->values();
        } catch (\Throwable) {
            return collect();
        }
    }

    /** Only web addresses, mail and phone links. Never "javascript:" and the like. */
    public static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return preg_match('#^(https?://|mailto:)#i', $url) ? $url : null;
    }

    public static function colour(string $platform): string
    {
        return self::PLATFORMS[$platform][1] ?? '#4B5563';
    }

    public static function drawing(string $platform): string
    {
        return self::PLATFORMS[$platform][2] ?? self::PLATFORMS['website'][2];
    }
}
