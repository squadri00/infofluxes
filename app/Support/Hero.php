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

    /** The shape of the full-size gallery: 2000 x 920 pixels. */
    public const BANNER_WIDTH = 2000;

    public const BANNER_HEIGHT = 920;

    /**
     * Any picture goes in; the full-size gallery shows a 2000 x 920 copy, cut to the right shape without squeezing it
     * (the largest part of the picture that fits, at full quality, never made bigger than it is).
     * The copy is made once and kept. 'focus' says which part to keep when the picture is too tall: top, middle or bottom.
     * Falls back to the original picture if anything goes wrong.
     */
    public static function bannerUrl(HeroSlide $slide): string
    {
        $original = asset('storage/'.$slide->image);
        $source = storage_path('app/public/'.ltrim((string) $slide->image, '/'));

        if (! function_exists('imagewebp') || ! is_file($source)) {
            return $original;
        }

        $focus = in_array($slide->custom_data['focus'] ?? 'middle', ['top', 'middle', 'bottom'], true) ? $slide->custom_data['focus'] ?? 'middle' : 'middle';
        $name = 'hero/banner/'.$slide->id.'-'.filemtime($source).'-'.$focus.'.webp';
        $target = storage_path('app/public/'.$name);

        if (is_file($target)) {
            return asset('storage/'.$name);
        }

        try {
            $info = @getimagesize($source);
            $image = match ($info[2] ?? 0) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
                IMAGETYPE_PNG => @imagecreatefrompng($source),
                IMAGETYPE_WEBP => @imagecreatefromwebp($source),
                IMAGETYPE_GIF => @imagecreatefromgif($source),
                default => false,
            };
            if (! $image) {
                return $original;
            }

            $sw = imagesx($image);
            $sh = imagesy($image);
            $want = static::BANNER_WIDTH / static::BANNER_HEIGHT;
            $cw = $sw;
            $ch = (int) round($sw / $want);
            if ($ch > $sh) {
                $ch = $sh;
                $cw = (int) round($sh * $want);
            }
            $cx = (int) floor(($sw - $cw) / 2);
            $cy = match ($focus) {
                'top' => 0,
                'bottom' => $sh - $ch,
                default => (int) floor(($sh - $ch) / 2),
            };

            $outW = min(static::BANNER_WIDTH, $cw);
            $outH = (int) round($outW / $want);
            $out = imagecreatetruecolor($outW, $outH);
            imagecopyresampled($out, $image, 0, 0, $cx, $cy, $outW, $outH, $cw, $ch);

            @mkdir(dirname($target), 0775, true);
            $ok = imagewebp($out, $target, 88);
            imagedestroy($image);
            imagedestroy($out);

            return $ok ? asset('storage/'.$name) : $original;
        } catch (\Throwable) {
            return $original;
        }
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
