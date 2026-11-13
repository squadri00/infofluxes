<?php

namespace App\Support;

use App\Models\HeroMessage;
use App\Models\HeroSlide;
use App\Models\Setting;
use Illuminate\Support\Collection;

/** The banner on the home page: words on one side (one set picked at random), and a picture slider on the other. */
class Hero
{
    public static function enabled(): bool
    {
        return (string) Setting::get('hero_on', '1') === '1';
    }

    /** 'split' = words beside the slider (usual); 'full' = a full-size gallery with the words on top of it. */
    public static function layout(): string
    {
        return Setting::get('hero_layout', 'split') === 'full' ? 'full' : 'split';
    }

    /** Width / height of a stored picture, kept between 1 and 2.4 so the frame is never odd. 16/9 when it cannot be read. */
    public static function ratio(?string $path): float
    {
        $file = $path ? storage_path('app/public/'.ltrim($path, '/')) : null;
        $size = ($file && is_file($file)) ? @getimagesize($file) : false;

        return ($size && $size[1] > 0) ? round(min(2.4, max(1.0, $size[0] / $size[1])), 4) : round(16 / 9, 4);
    }

    /** Seconds before the next picture. 0 means it never moves by itself. */
    public static function seconds(): int
    {
        return min(60, max(0, (int) Setting::get('hero_slide_seconds', 6)));
    }

    /** One active set of words, picked at random each time. */
    public static function message(): ?HeroMessage
    {
        return HeroMessage::query()->where('is_active', true)->inRandomOrder()->first();
    }

    /** The active pictures in order. */
    public static function slides(): Collection
    {
        return HeroSlide::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    /** The banner for the home page, or null when it is off or has nothing to show. */
    public static function forHome(): ?array
    {
        if (! static::enabled()) {
            return null;
        }

        $message = static::message();
        $slides = static::slides();

        return ($message || $slides->isNotEmpty()) ? ['message' => $message, 'slides' => $slides, 'seconds' => static::seconds(), 'layout' => static::layout()] : null;
    }

    /** Only ordinary web, mail and phone links, or a path on this site. Never "javascript:" and the like. */
    public static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        return preg_match('#^(https?://|mailto:|tel:)#i', $url) || (str_starts_with($url, '/') && ! str_starts_with($url, '//')) ? $url : null;
    }

    /** The address of a slide's button: a path on this site becomes a full address. */
    public static function link(?string $url): ?string
    {
        $safe = static::safeUrl($url);

        return $safe !== null && str_starts_with($safe, '/') ? url($safe) : $safe;
    }
}
