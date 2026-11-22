<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Small copies of the pictures for the picture gallery, so a page of 100 pictures is light (about 50 KB each, not 200 KB).
 * A copy is made the first time it is needed and kept in the "thumbs" folder of the public storage. If it cannot be made, the
 * picture itself is used.
 */
class Thumbnail
{
    public const WIDTH = 800;

    /** At most this many copies are made while one page is shown (the rest use the full picture this time and get their copy on a later visit). */
    private const BUDGET = 120;

    private static int $made = 0;

    public static function path(string $source, int $width = self::WIDTH): string
    {
        return 'thumbs/'.sha1($source).'-'.$width.'.webp';
    }

    /** The web address of the small copy of a stored picture (made now when it is not there yet). */
    public static function url(string $source, int $width = self::WIDTH): string
    {
        $disk = Storage::disk(ImageStore::DISK);
        $thumb = static::path($source, $width);

        if ($disk->exists($thumb)) {
            return asset('storage/'.$thumb);
        }
        if (static::$made < self::BUDGET && static::make($source, $thumb, $width)) {
            static::$made++;

            return asset('storage/'.$thumb);
        }

        return asset('storage/'.$source);
    }

    private static function make(string $source, string $thumb, int $width): bool
    {
        $disk = Storage::disk(ImageStore::DISK);
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring') || ! $disk->exists($source)) {
            return false;
        }
        try {
            $image = @imagecreatefromstring((string) $disk->get($source));
            if (! $image) {
                return false;
            }
            if (imagesx($image) > $width) {
                $image = imagescale($image, $width, -1, IMG_BICUBIC) ?: $image;
            }
            imagepalettetotruecolor($image);
            ob_start();
            imagewebp($image, null, 72);
            $data = (string) ob_get_clean();
            if ($data === '') {
                return false;
            }
            $disk->put($thumb, $data, 'public');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
