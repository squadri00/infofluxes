<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One English (or other) translation of the Quran: its name, who made it, where it came from, and the notice that must stay with it. */
class QuranTranslation extends Model
{
    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean'];

    public function verses(): HasMany
    {
        return $this->hasMany(QuranTranslationVerse::class, 'translation_id');
    }
}
