<?php

namespace App\Models\Concerns;

use App\Models\Reaction;
use App\Models\SavedItem;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Likes, dislikes and saves of an article or a story. They go away when the item is deleted for good. */
trait HasEngagement
{
    public static function bootHasEngagement(): void
    {
        static::forceDeleted(function ($model) {
            $model->reactions()->delete();
            $model->saves()->delete();
        });
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function saves(): MorphMany
    {
        return $this->morphMany(SavedItem::class, 'saveable');
    }

    /** Adds likes_count and dislikes_count to a list in one go (so a card can show them without one query each). */
    public function scopeWithEngagement($query)
    {
        return $query->withCount([
            'reactions as likes_count' => fn ($q) => $q->where('value', Reaction::LIKE),
            'reactions as dislikes_count' => fn ($q) => $q->where('value', Reaction::DISLIKE),
        ]);
    }

    public function likesCount(): int
    {
        return $this->reactions()->where('value', Reaction::LIKE)->count();
    }

    public function dislikesCount(): int
    {
        return $this->reactions()->where('value', Reaction::DISLIKE)->count();
    }
}
