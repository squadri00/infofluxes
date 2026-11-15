<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/** One "Did You Know?" entry: a short fact with its source. Code D-<number>. */
class DidYouKnow extends Model
{
    use HasTranslations, \App\Models\Concerns\LogsActivity, \App\Models\Concerns\KeepsOldAddress;

    public function publicPath(?string $slug = null): string
    {
        return '/did-you-know/'.($slug ?? $this->slug);
    }

    protected $table = 'did_you_know_entries';

    protected $guarded = [];

    public array $translatable = ['title', 'body'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    protected function casts(): array
    {
        return ['ai_assisted' => 'boolean', 'custom_data' => 'array'];
    }

    protected static function booted(): void
    {
        // Every fact gets an address from its heading, and keeps it when the heading is changed later.
        static::saving(function (self $fact) {
            if (blank($fact->slug)) {
                $fact->slug = static::uniqueSlug((string) $fact->getTranslation('title', config('infofluxes.default_locale'), true), $fact->id);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $exceptId = null): string
    {
        $base = \Illuminate\Support\Str::slug($title) ?: 'fact';
        $slug = $base;
        for ($n = 2; static::query()->where('slug', $slug)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /** The page of this fact. */
    public function url(): string
    {
        return route('did-you-know.show', $this->slug);
    }

    /** The first words of the answer as plain text, for a card. */
    public function summary(int $limit = 150): string
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('~</(p|h[1-6]|li|td|th|tr|div)>~i', ' ', (string) $this->body)), ENT_QUOTES | ENT_HTML5)));

        return \Illuminate\Support\Str::limit($text, $limit);
    }

    /** The visible facts that sit in a category or below it, grouped by their category (for the tree beside a page). */
    public static function forBranch(Category $root): \Illuminate\Support\Collection
    {
        return static::query()->visible()->whereIn('category_id', $root->descendantIds())->ordered()->get()->groupBy('category_id');
    }

    /** May a visitor see it? Published, and with a source. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('source');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The article that tells the whole story, when the fact points to one. */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function getCodeAttribute(): string
    {
        return 'D-'.$this->id;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** A web address given as the source is a link; any other words are shown as they are. */
    public function sourceUrl(): ?string
    {
        $source = trim((string) $this->source);

        return preg_match('#^https?://\S+$#i', $source) ? $source : null;
    }
}
