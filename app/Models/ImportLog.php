<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One saved AI paste: what was pasted and what it made. */
class ImportLog extends Model
{
    protected $table = 'imports';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['result' => 'array', 'custom_data' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
