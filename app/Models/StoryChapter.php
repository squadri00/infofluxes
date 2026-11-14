<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/** A chapter of a Visual Story. */
class StoryChapter extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['title'];

    protected function casts(): array
    {
        return ['custom_data' => 'array'];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function slides(): HasMany
    {
        return $this->hasMany(StorySlide::class)->orderBy('sort_order')->orderBy('id');
    }
}
