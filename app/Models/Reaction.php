<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A like (1) or a dislike (-1) given by a person to an article or a story. */
class Reaction extends Model
{
    public const LIKE = 1;

    public const DISLIKE = -1;

    protected $fillable = ['user_id', 'value', 'custom_data'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'custom_data' => 'array'];
    }

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
