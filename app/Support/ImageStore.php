<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Every picture you upload (logo, category and contributor pictures, home slider, facts, gallery) is saved as a WebP that is
 * no heavier than the set size (usually 300 KB), so the site loads fast, also on a phone. To get there it first lowers the quality a little,
 * and only if that is not enough does it make the picture narrower (never wider than it was). Phone photos stored sideways are turned.
 * Kept as they are: an animated GIF (it would lose its movement), and a WebP that is already light and narrow enough (saving it again
 * would only lose quality). If a file cannot be read as a picture, it is kept exactly as it is.
 * The story pictures have their own, stricter rules (App\Support\StoryImage).
 */
class ImageStore
{
    public const DISK = 'public';

    /** The kinds of picture that are accepted. SVG is never accepted (it can hide scripts). */
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** The narrowest a picture may be made to reach the size limit. */
    private const NARROWEST = 640;

    /** The most a person may upload, in kilobytes. 12 MB is also the limit of the upload step itself, so more would not get through. */
    public static function maxKb(): int
    {
        $mb = (int) Setting::get('upload_max_mb', 12);

        return ($mb >= 1 ? min(12, $mb) : 12) * 1024;
    }

    /** How heavy a saved picture may be, in bytes (usually 300 KB). */
    public static function maxBytes(): int
    {
        $kb = (int) Setting::get('image_max_kb', 300);

        return ($kb >= 50 ? min(1500, $kb) : 300) * 1024;
    }

    public static function maxWidth(): int
    {
        $w = (int) Setting::get('image_max_width', 2400);

        return $w >= 400 ? min(6000, $w) : 2400;
    }

    /** The best quality tried first. It goes down from here until the picture fits. */
    public static function quality(): int
    {
        $q = (int) Setting::get('image_quality', 85);

        return $q >= 40 ? min(95, $q) : 85;
    }

    /** Save the file in a folder of the public disk and return its path. */
    /**
     * Save the file in a folder of the public disk and return its path. The file name is made from $subject (the title or name of the
     * page the picture belongs to), see App\Support\PictureNames; without a subject it is made from the name of the uploaded file.
     */
    public static function store(UploadedFile|string $file, string $directory, ?string $subject = null): string
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $binary = (string) file_get_contents($path);
        $directory = trim($directory, '/');
        $disk = Storage::disk(static::DISK);
        $subject = $subject !== null && trim($subject) !== '' ? $subject : PictureNames::fromUpload($file);

        $webp = static::lighten($binary);
        if ($webp !== null) {
            $name = PictureNames::path($directory, $subject, 'webp');
            $disk->put($name, $webp, 'public');

            return $name;
        }

        // Kept as it is (an animated GIF, a WebP that is light enough already, or something that is not a picture), with a safe extension.
        $info = @getimagesizefromstring($binary);
        $ext = match ($info[2] ?? 0) {
            IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif', default => 'bin',
        };
        $name = PictureNames::path($directory, $subject, $ext);
        $disk->put($name, $binary, 'public');

        return $name;
    }

    /**
     * The picture as a WebP that fits the size limit, or null when the original is to be kept (see the top of this file).
     *
     * @param  int|null  $maxBytes  another limit than the usual one (for the tool that lightens the pictures already stored)
     */
    public static function lighten(string $binary, ?int $maxBytes = null): ?string
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $info = @getimagesizefromstring($binary);
        if (! $info || ! in_array($info['mime'] ?? '', static::TYPES, true)) {
            return null;
        }
        $maxBytes ??= static::maxBytes();
        $limitWidth = static::maxWidth();

        // An animated GIF would lose its movement: leave it alone.
        if (($info[2] ?? 0) === IMAGETYPE_GIF && substr_count($binary, "\x21\xF9\x04") > 1) {
            return null;
        }
        // A WebP that is already light and narrow enough: saving it again would only cost quality.
        if (($info['mime'] ?? '') === 'image/webp' && strlen($binary) <= $maxBytes && ($info[0] ?? 0) <= $limitWidth) {
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
                $image = $rotated;
            }
        }
        imagepalettetotruecolor($image);

        $w = imagesx($image);
        $h = imagesy($image);
        $width = min($w, $limitWidth);

        // The qualities to try, best first: the set one, then lower steps, never below 40.
        $top = static::quality();
        $steps = array_values(array_unique(array_filter([$top, 75, 65, 55, 45, 40], fn ($q) => $q <= $top && $q >= 40)));

        $best = null;
        while (true) {
            $frame = static::resize($image, $w, $h, $width);
            foreach ($steps as $quality) {
                $data = static::encode($frame, $quality);
                if ($data !== null && ($best === null || strlen($data) < strlen($best))) {
                    $best = $data;
                }
                if ($data !== null && strlen($data) <= $maxBytes) {
                    return $data;
                }
            }

            // Still too heavy at the lowest quality: make the picture narrower and try again.
            if ($width <= static::NARROWEST) {
                break;
            }
            $width = max(static::NARROWEST, (int) round($width * 0.85));
        }

        // Could not get under the limit even at the narrowest: the lightest version found is still the best one to use.
        return $best;
    }

    /** A copy of the picture at this width (the same shape), see-through parts kept. The picture itself when it is not made narrower. */
    private static function resize(\GdImage $image, int $w, int $h, int $width): \GdImage
    {
        if ($width >= $w) {
            imagealphablending($image, false);
            imagesavealpha($image, true);

            return $image;
        }
        $height = max(1, (int) round($h * $width / $w));
        $small = imagecreatetruecolor($width, $height);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagecopyresampled($small, $image, 0, 0, 0, 0, $width, $height, $w, $h);

        return $small;
    }

    private static function encode(\GdImage $frame, int $quality): ?string
    {
        ob_start();
        $ok = imagewebp($frame, null, $quality);
        $data = (string) ob_get_clean();

        return $ok && $data !== '' ? $data : null;
    }
}
