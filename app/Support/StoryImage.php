<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The pictures of a visual story: any normal picture goes in, a 4:3 WebP of about 200 KB comes out.
 * The width, the first quality and the size to aim for are Settings. SVG is never accepted (it can hide scripts).
 */
class StoryImage
{
    public const DISK = 'public';

    public const DIRECTORY = 'stories';

    public const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** The shape of the pictures: 16:9 (the shape of a 1920 x 1080 picture) or 4:3. A Setting; 16:9 is the default. */
    public static function ratio(): array
    {
        return (string) Setting::get('story_image_ratio', '16:9') === '4:3' ? [4, 3] : [16, 9];
    }

    public static function ratioText(): string
    {
        return implode(':', static::ratio());
    }

    /** The height of a picture of this width. */
    public static function heightFor(int $width): int
    {
        [$w, $h] = static::ratio();

        return (int) round($width * $h / $w);
    }

    /** The file is stored as a WebP and its path (inside the public disk) is returned. Its name is made from $subject (the story and the slide), see App\Support\PictureNames. */
    public static function store(UploadedFile|string $file, ?string $subject = null): string
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $result = static::convert((string) file_get_contents($path));

        $subject = $subject !== null && trim($subject) !== '' ? $subject : PictureNames::fromUpload($file);
        $name = PictureNames::path(static::DIRECTORY, $subject, 'webp', 'story-picture');
        Storage::disk(static::DISK)->put($name, $result['data'], 'public');

        return $name;
    }

    /**
     * @return array{data: string, width: int, height: int, quality: int, bytes: int}
     */
    public static function convert(string $binary): array
    {
        if (! function_exists('imagewebp')) {
            throw ValidationException::withMessages(['image' => 'This server cannot make WebP pictures (the GD library with WebP is missing).']);
        }

        $info = @getimagesizefromstring($binary);
        if (! $info || ! in_array($info['mime'] ?? '', static::TYPES, true)) {
            throw ValidationException::withMessages(['image' => 'Use a JPG, PNG, WebP or GIF picture. SVG pictures are not accepted.']);
        }

        $source = @imagecreatefromstring($binary);
        if (! $source) {
            throw ValidationException::withMessages(['image' => 'This picture could not be read.']);
        }

        $maxWidth = max(400, (int) Setting::get('story_image_width', 1200));
        $targetBytes = max(30, (int) Setting::get('story_image_kb', 200)) * 1024;
        $quality = min(95, max(40, (int) Setting::get('story_image_quality', 80)));

        $image = static::cropToRatio($source);
        if (imagesx($image) > $maxWidth) {
            $image = imagescale($image, $maxWidth, static::heightFor($maxWidth), IMG_BICUBIC);
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Lower the quality, and then the width, until the picture is small enough.
        while (true) {
            ob_start();
            imagewebp($image, null, $quality);
            $data = (string) ob_get_clean();

            if (strlen($data) <= $targetBytes) {
                break;
            }
            if ($quality > 45) {
                $quality -= 10;

                continue;
            }
            if (imagesx($image) <= 600) {
                break;                                       // as small as we go
            }
            $width = (int) round(imagesx($image) * 0.85);
            $image = imagescale($image, $width, static::heightFor($width), IMG_BICUBIC);
        }

        return ['data' => $data, 'width' => imagesx($image), 'height' => imagesy($image), 'quality' => $quality, 'bytes' => strlen($data)];
    }

    /** Cut a picture to the shape from the middle. A picture that already has the shape stays as it is. */
    private static function cropToRatio(\GdImage $image): \GdImage
    {
        [$rw, $rh] = static::ratio();
        $w = imagesx($image);
        $h = imagesy($image);

        if (abs($w * $rh - $h * $rw) <= $rw) {
            return $image;
        }

        if ($w * $rh > $h * $rw) {            // too wide: cut the sides
            $newW = (int) round($h * $rw / $rh);
            $x = (int) floor(($w - $newW) / 2);
            $crop = imagecrop($image, ['x' => $x, 'y' => 0, 'width' => $newW, 'height' => $h]);
        } else {                              // too tall: cut the top and the bottom
            $newH = (int) round($w * $rh / $rw);
            $y = (int) floor(($h - $newH) / 2);
            $crop = imagecrop($image, ['x' => 0, 'y' => $y, 'width' => $w, 'height' => $newH]);
        }

        return $crop ?: $image;
    }
}
