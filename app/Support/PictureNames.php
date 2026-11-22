<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The file name of a picture: made from the page it belongs to (the article title, the category name, the story and the slide ...),
 * for example "ibn-battuta-the-muslim-traveller-a3f9k2.webp". Search engines read the file name, so it helps in Google Images.
 * A short random ending keeps two pictures of the same title apart and keeps a browser from showing an old cached picture.
 */
class PictureNames
{
    /** The longest readable part of a name (the ending and the extension come after it). */
    public const MAX = 70;

    /** A text as a safe file name part: small letters, numbers and dashes. Empty when there is nothing usable. */
    public static function slug(?string $text, int $max = self::MAX): string
    {
        $slug = Str::slug(strip_tags((string) $text));
        if (mb_strlen($slug) > $max) {
            $slug = mb_substr($slug, 0, $max);
            // Cut at a whole word when there is one near the end.
            if (($cut = mb_strrpos($slug, '-')) !== false && $cut > $max * 0.6) {
                $slug = mb_substr($slug, 0, $cut);
            }
        }

        return trim($slug, '-');
    }

    /** "folder/readable-name-a3f9k2.webp" that is not used yet in the public folder. */
    public static function path(string $directory, ?string $subject, string $extension = 'webp', string $fallback = 'picture'): string
    {
        $base = static::slug($subject) ?: $fallback;
        $disk = Storage::disk(ImageStore::DISK);
        $directory = trim($directory, '/');
        do {
            $path = $directory.'/'.$base.'-'.Str::lower(Str::random(6)).'.'.$extension;
        } while ($disk->exists($path));

        return $path;
    }

    /** Is this file name already made from this subject? (Then it is left alone when pictures are renamed.) */
    public static function isNamedFor(string $path, ?string $subject): bool
    {
        $slug = static::slug($subject);

        return $slug !== '' && str_starts_with(pathinfo($path, PATHINFO_FILENAME), $slug.'-');
    }

    /** The name the person gave the file, as a subject: "IMG_2041.JPG" gives "img-2041". Used only when the page has no title yet. */
    public static function fromUpload(UploadedFile|string $file): ?string
    {
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);
        $name = pathinfo($name, PATHINFO_FILENAME);

        return static::slug($name) ?: null;
    }

    /**
     * The subject for a picture uploaded in an admin form: the title or name of the record that is being edited, or else of
     * what has been typed in the form so far.
     *
     * @param  mixed  $livewire  the page that holds the form
     * @param  ?\Filament\Schemas\Components\Utilities\Get  $get
     */
    public static function fromForm(mixed $livewire, mixed $get = null): ?string
    {
        $record = is_object($livewire) && method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
        if ($record && ($label = static::labelOf($record))) {
            return $label;
        }
        if ($get) {
            $locale = config('infofluxes.default_locale');
            foreach (["title.$locale", "name.$locale", "heading.$locale", 'title', 'name', 'heading', "../../title.$locale", "../../name.$locale"] as $path) {
                $value = $get($path);
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /** What a record is called, for a picture file name. */
    public static function labelOf(object $model): ?string
    {
        return match (true) {
            $model instanceof \App\Models\Article, $model instanceof \App\Models\Story => $model->mainTitle(),
            $model instanceof \App\Models\Category => $model->mainName(),
            $model instanceof \App\Models\Person => (string) $model->name,
            $model instanceof \App\Models\DidYouKnow => (string) $model->title,
            $model instanceof \App\Models\HeroSlide => (string) $model->heading,
            default => null,
        };
    }
}
