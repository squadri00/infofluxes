<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A Visual Story: a picture story told in chapters and slides, with a cover, Listen audio and a PDF book.
 * Its code (S-7) is made from the id, so it never changes and is never used twice.
 * The columns have the same names as an article's, so the story viewer, Listen and the book read a story as they read an article.
 */
class Story extends Model
{
    use HasTranslations, SoftDeletes, \App\Models\Concerns\HasEngagement, \App\Models\Concerns\LogsActivity, \App\Models\Concerns\KeepsOldAddress;

    public function publicPath(?string $slug = null): string
    {
        return '/stories/'.($slug ?? $this->slug);
    }

    public array $activityUndo = ['source_line', 'search_words'];

    protected $guarded = [];

    public const STATUSES = [
        'draft' => 'Draft',
        'in_review' => 'In review',
        'published' => 'Published',
        'archived' => 'Archived',
    ];

    public array $translatable = ['title', 'short_detail', 'long_detail', 'main_image_alt', 'page_title', 'meta_description'];

    protected static function booted(): void
    {
        static::saving(function (Story $story) {
            if ($story->isDirty('audio') && ! $story->isDirty('audio_state')) {
                $story->audio_state = 'draft';
            }
            if ($story->isDirty('cover_audio') && ! $story->isDirty('cover_audio_state')) {
                $story->cover_audio_state = 'draft';
            }
        });
    }

    protected function casts(): array
    {
        return ['search_words' => 'array', 'custom_data' => 'array', 'publish_date' => 'datetime', 'ai_assisted' => 'boolean', 'is_featured' => 'boolean'];
    }

    /** The main category. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The other categories it also sits in. */
    public function extraCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'story_category');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'contributor_id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(StoryChapter::class)->orderBy('sort_order')->orderBy('id');
    }

    public function slides(): HasManyThrough
    {
        return $this->hasManyThrough(StorySlide::class, StoryChapter::class);
    }

    /** Does the story have any slides? */
    public function hasSlides(): bool
    {
        return isset($this->attributes['slides_count']) ? (int) $this->attributes['slides_count'] > 0 : $this->slides()->exists();
    }

    public function audioIsLive(): bool
    {
        return filled($this->audio) && $this->audio_state === 'approved';
    }

    public function audioUrl(): ?string
    {
        return filled($this->audio) ? asset('storage/'.$this->audio) : null;
    }

    public function coverAudioIsLive(): bool
    {
        return filled($this->cover_audio) && $this->cover_audio_state === 'approved';
    }

    public function coverAudioUrl(): ?string
    {
        return filled($this->cover_audio) ? asset('storage/'.$this->cover_audio) : null;
    }

    public function getCodeAttribute(): string
    {
        return 'S-'.$this->id;
    }

    /** The page of this story. */
    public function url(): string
    {
        return route('story.show', $this->slug);
    }

    /** The title in the main language (used in admin lists). */
    public function mainTitle(): string
    {
        return (string) ($this->getTranslation('title', config('infofluxes.default_locale'), false) ?: $this->getTranslation('title', config('infofluxes.default_locale')));
    }

    /** Every category it sits in: the main one first, then the others. */
    public function allCategories()
    {
        return collect([$this->category])->filter()->concat($this->extraCategories)->unique('id')->values();
    }

    /** A web address made from a title: never a reserved word, never one already used by a story, an article or a page. */
    public static function uniqueSlug(string $title): string
    {
        $base = \Illuminate\Support\Str::slug($title) ?: 'story';
        $slug = $base;
        $n = 2;
        while (static::withTrashed()->where('slug', $slug)->exists() || Article::withTrashed()->where('slug', $slug)->exists() || in_array($slug, Category::RESERVED_SLUGS, true) || in_array($slug, Page::RESERVED, true) || Page::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /** Featured stories that visitors may see, in the order set on the stories (1 first), then the newest. */
    public function scopeFeaturedOnHome(Builder $query): Builder
    {
        return $query->published()->where('is_featured', true)->orderByRaw('CASE WHEN featured_order = 0 THEN 1 ELSE 0 END')->orderBy('featured_order')->orderByDesc('publish_date')->orderByDesc('id');
    }

    /** What visitors may see: published, and its publish date (if any) has come. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where(fn ($q) => $q->whereNull('publish_date')->orWhere('publish_date', '<=', now()));
    }
}
