<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An old file name of a picture and the new one. Visitors, search engines and shared links that still use the old web address are sent to the new one. */
class PictureRedirect extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['custom_data' => 'array'];
    }

    /** Remember that a picture moved. Redirects that already pointed to the old name now point to the new one, so there is never a chain. */
    public static function remember(string $old, string $new): void
    {
        $old = ltrim($old, '/');
        $new = ltrim($new, '/');
        if ($old === $new) {
            return;
        }
        static::query()->where('new_path', $old)->update(['new_path' => $new]);
        static::query()->updateOrCreate(['old_path' => $old], ['new_path' => $new]);
        // The picture keeps its gallery ID (I-12) and its on/off switch under the new name.
        if (! GalleryPicture::query()->where('path', $new)->exists()) {
            GalleryPicture::query()->where('path', $old)->update(['path' => $new]);
            \App\Support\Gallery::forget();
        }
    }

    /** Where does an old picture address go? Null when it is not known. */
    public static function target(string $path): ?string
    {
        return static::query()->where('old_path', ltrim($path, '/'))->value('new_path');
    }
}
