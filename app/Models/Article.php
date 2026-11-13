<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/** An article. The code (A-42) is made from the id, so it never changes and is never used twice. */
class Article extends Model
{
    use HasTranslations, SoftDeletes;

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
        static::saving(function (Article $article) {
            if ($article->isDirty('audio') && ! $article->isDirty('audio_state')) {
                $article->audio_state = 'draft';
            }
            if ($article->isDirty('cover_audio') && ! $article->isDirty('cover_audio_state')) {
                $article->cover_audio_state = 'draft';
            }
        });
    }

    protected function casts(): array
    {
        return ['search_words' => 'array', 'custom_data' => 'array', 'publish_date' => 'datetime', 'ai_assisted' => 'boolean', 'is_index_line' => 'boolean', 'is_featured' => 'boolean'];
    }

    /** The main category. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The other categories it also sits in. */
    public function extraCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_category');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** The contributor credited on the article (a person with a page of their own, or just a name). */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'contributor_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ArticleImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ArticleVideo::class)->orderBy('sort_order')->orderBy('id');
    }

    /** A web address made from a title: never a reserved word, never one already used. */
    public static function uniqueSlug(string $title): string
    {
        $base = \Illuminate\Support\Str::slug($title) ?: 'article';
        $slug = $base;
        $n = 2;
        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, Category::RESERVED_SLUGS, true) || in_array($slug, Page::RESERVED, true) || Page::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(ArticleChapter::class)->orderBy('sort_order')->orderBy('id');
    }

    public function slides(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(ArticleSlide::class, ArticleChapter::class);
    }

    /** Does the article have any slides? (Then its page shows the slide viewer above the text.) */
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

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function getCodeAttribute(): string
    {
        return 'A-'.$this->id;
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

    /** Featured articles that visitors may see, in the order set on the articles (1 first), then the newest. */
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
