<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * A website page: About, Contact, Privacy, Terms, FAQ and so on. Its code is P-<number>.
 * It opens at /its-address, next to the articles. The address of a page never equals the address of an article or of a part of the site.
 */
class Page extends Model
{
    use HasTranslations, \App\Models\Concerns\LogsActivity, \App\Models\Concerns\KeepsOldAddress;

    public function publicPath(?string $slug = null): string
    {
        return '/'.($slug ?? $this->slug);
    }

    protected $guarded = [];

    public array $translatable = ['title', 'body', 'meta_description'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    /** Addresses the site itself uses. A page may not take one of them. */
    public const RESERVED = [
        'admin', 'login', 'register', 'logout', 'account', 'submit', 'search', 'category', 'id', 'language', 'sitemap', 'sitemap-xml',
        'challenge-yourself', 'quiz', 'did-you-know', 'stories', 'gallery', 'categories', 'articles', 'words', 'faq', 'people', 'newsletter', 'comments', 'storage', 'css', 'js', 'images', 'livewire', 'up', 'robots-txt', 'build', 'vendor',
    ];

    protected function casts(): array
    {
        return ['in_footer' => 'boolean', 'no_index' => 'boolean', 'custom_data' => 'array'];
    }

    public function getCodeAttribute(): string
    {
        return 'P-'.$this->id;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** A free address for a page: not used by another page, an article or a part of the site. */
    public static function uniqueSlug(string $wanted, ?int $exceptId = null): string
    {
        $base = Str::slug($wanted) ?: 'page';
        $slug = $base;
        $n = 2;
        while (static::slugTaken($slug, $exceptId)) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public static function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        return in_array($slug, static::RESERVED, true)
            || static::query()->where('slug', $slug)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->exists()
            || Article::withTrashed()->where('slug', $slug)->exists();
    }
}
