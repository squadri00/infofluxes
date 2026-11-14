<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** An article or a story that a person saved to read later. */
class SavedItem extends Model
{
    protected $fillable = ['user_id', 'custom_data'];

    protected function casts(): array
    {
        return ['custom_data' => 'array'];
    }

    public function saveable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
