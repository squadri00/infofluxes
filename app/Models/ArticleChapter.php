<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/** A chapter of the story slides of an article. */
class ArticleChapter extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['title'];

    protected function casts(): array
    {
        return ['custom_data' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function slides(): HasMany
    {
        return $this->hasMany(ArticleSlide::class)->orderBy('sort_order')->orderBy('id');
    }
}
