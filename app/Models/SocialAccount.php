<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The Google or Facebook account a person signs in with. */
class SocialAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['custom_data' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
