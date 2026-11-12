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

    protected function casts(): array
    {
        return ['search_words' => 'array', 'custom_data' => 'array', 'publish_date' => 'datetime', 'ai_assisted' => 'boolean', 'is_index_line' => 'boolean'];
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
        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, Category::RESERVED_SLUGS, true)) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
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

    /** What visitors may see: published, and its publish date (if any) has come. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where(fn ($q) => $q->whereNull('publish_date')->orWhere('publish_date', '<=', now()));
    }
}
