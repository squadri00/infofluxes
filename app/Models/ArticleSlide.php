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

    protected static function booted(): void
    {
        // A new or changed audio file waits for approval again, unless the state is set in the same save.
        static::saving(function (ArticleSlide $slide) {
            if ($slide->isDirty('audio') && ! $slide->isDirty('audio_state')) {
                $slide->audio_state = 'draft';
            }
        });
    }

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

    /** An audio file plays only when a person has approved it. */
    public function audioIsLive(): bool
    {
        return filled($this->audio) && $this->audio_state === 'approved';
    }

    public function audioUrl(): ?string
    {
        return filled($this->audio) ? asset('storage/'.$this->audio) : null;
    }

    public function imageUrl(): ?string
    {
        return $this->hasImage() ? asset('storage/'.$this->image) : null;
    }
}
