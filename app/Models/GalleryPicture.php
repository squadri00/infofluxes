<?php

namespace App\Models;

use App\Support\Gallery;
use Illuminate\Database\Eloquent\Model;

/** One picture of the picture gallery, with its own ID (I-12) and a switch to show it or leave it out. */
class GalleryPicture extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_shown' => 'boolean', 'present' => 'boolean', 'custom_data' => 'array'];
    }

    protected static function booted(): void
    {
        // The gallery keeps its list for a few minutes; a switch must show at once.
        static::saved(fn () => Gallery::forget());
        static::deleted(fn () => Gallery::forget());
    }

    /** "I-12": the ID shown on the picture. */
    public function getCodeAttribute(): string
    {
        return 'I-'.$this->id;
    }
}
