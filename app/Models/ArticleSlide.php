<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/** One slide: a picture (optional), a heading and a few words. */
class ArticleSlide extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['image_alt', 'heading', 'body'];

    protected function casts(): array
    {
        return ['custom_data' => 'array'];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(ArticleChapter::class, 'article_chapter_id');
    }

    public function hasImage(): bool
    {
        return filled($this->image);
    }

    public function imageUrl(): ?string
    {
        return $this->hasImage() ? asset('storage/'.$this->image) : null;
    }
}
