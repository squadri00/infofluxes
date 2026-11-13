<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The pictures you upload (logo, category and contributor pictures, home slider, facts, gallery) are made lighter when they are saved:
 * a picture wider than the set width is made narrower (never wider than it was), turned the right way up, and saved as a WebP.
 * A big photo of several megabytes becomes a few hundred kilobytes without a visible loss. If anything goes wrong the original is kept as it is.
 * The story pictures have their own, stricter rules (App\Support\StoryImage).
 */
class ImageStore
{
    public const DISK = 'public';

    /** The kinds of picture that are accepted. SVG is never accepted (it can hide scripts). */
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** The most a person may upload, in kilobytes. 12 MB is also the limit of the upload step itself, so more would not get through. */
    public static function maxKb(): int
    {
        $mb = (int) Setting::get('upload_max_mb', 12);

        return ($mb >= 1 ? min(12, $mb) : 12) * 1024;
    }

    public static function maxWidth(): int
    {
        $w = (int) Setting::get('image_max_width', 2400);

        return $w >= 400 ? min(6000, $w) : 2400;
    }

    public static function quality(): int
    {
        $q = (int) Setting::get('image_quality', 85);

        return $q >= 40 ? min(95, $q) : 85;
    }

    /** Save the file in a folder of the public disk and return its path. */
    public static function store(UploadedFile|string $file, string $directory): string
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $binary = (string) file_get_contents($path);
        $directory = trim($directory, '/');
        $disk = Storage::disk(static::DISK);

        $webp = static::lighten($binary);
        if ($webp !== null) {
            $name = $directory.'/'.Str::lower(Str::random(24)).'.webp';
            $disk->put($name, $webp, 'public');

            return $name;
        }

        // Could not be made lighter: keep it exactly as it is, with a safe extension.
        $info = @getimagesizefromstring($binary);
        $ext = match ($info[2] ?? 0) {
            IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif', default => 'bin',
        };
        $name = $directory.'/'.Str::lower(Str::random(24)).'.'.$ext;
        $disk->put($name, $binary, 'public');

        return $name;
    }

    /** The picture as a lighter WebP, or null when that cannot be done (then the original is kept). */
    public static function lighten(string $binary): ?string
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $info = @getimagesizefromstring($binary);
        if (! $info || ! in_array($info['mime'] ?? '', static::TYPES, true)) {
            return null;
        }
        // An animated GIF would lose its movement: leave it alone.
        if (($info[2] ?? 0) === IMAGETYPE_GIF && substr_count($binary, "\x21\xF9\x04") > 1) {
            return null;
        }

        $image = @imagecreatefromstring($binary);
        if (! $image) {
            return null;
        }

        // A phone photo is often stored sideways with a note saying how to turn it: turn it.
        if (($info['mime'] ?? '') === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
            $turn = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => 180, 6 => -90, 8 => 90, default => 0,
            };
            if ($turn !== 0 && ($rotated = @imagerotate($image, $turn, 0))) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $max = static::maxWidth();
        $resized = $w > $max;
        if ($resized) {
            $newH = (int) round($h * $max / $w);
            $small = imagecreatetruecolor($max, $newH);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
            imagecopyresampled($small, $image, 0, 0, 0, 0, $max, $newH, $w, $h);
            imagedestroy($image);
            $image = $small;
        } else {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        ob_start();
        $ok = imagewebp($image, null, static::quality());
        $data = (string) ob_get_clean();
        imagedestroy($image);

        // A picture that was made narrower is always used (too wide is slow to show). Otherwise it is only worth it when it really got lighter.
        if (! $ok || $data === '' || (! $resized && strlen($data) >= strlen($binary))) {
            return null;
        }

        return $data;
    }
}
