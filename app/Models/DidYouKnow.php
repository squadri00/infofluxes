<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/** One "Did You Know?" entry: a short fact with its source. Code D-<number>. */
class DidYouKnow extends Model
{
    use HasTranslations;

    protected $table = 'did_you_know_entries';

    protected $guarded = [];

    public array $translatable = ['title', 'body'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    protected function casts(): array
    {
        return ['ai_assisted' => 'boolean', 'custom_data' => 'array'];
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
