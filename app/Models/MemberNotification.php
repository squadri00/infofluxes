<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A notice for a member: shown on the site (the bell) and, for news from what they follow, in the weekly email. */
class MemberNotification extends Model
{
    protected $table = 'member_notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'emailed_at' => 'datetime', 'custom_data' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isNew(): bool
    {
        return $this->read_at === null;
    }
}
