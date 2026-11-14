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

    public function likesCount(): int
    {
        return $this->reactions()->where('value', Reaction::LIKE)->count();
    }

    public function dislikesCount(): int
    {
        return $this->reactions()->where('value', Reaction::DISLIKE)->count();
    }
}
