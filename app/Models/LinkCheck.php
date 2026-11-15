<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One source link of an article and what the last check found. */
class LinkCheck extends Model
{
    protected $guarded = [];

    public const STATUSES = [
        'new' => 'Not checked yet',
        'ok' => 'Opens',
        'broken' => 'Broken',
        'blocked' => 'Blocked: open it yourself',
        'skipped' => 'Not a web address we may visit',
    ];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'failed_since' => 'datetime', 'manual_ok_at' => 'datetime', 'queued' => 'boolean', 'ignored' => 'boolean', 'custom_data' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** The links that need a person: broken, or blocked and not yet opened by hand. Ignored ones are left out. */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where('ignored', false)->where(fn ($q) => $q->where('status', 'broken')->orWhere(fn ($b) => $b->where('status', 'blocked')->whereNull('manual_ok_at')));
    }

    public function scopeBroken(Builder $query): Builder
    {
        return $query->where('ignored', false)->where('status', 'broken');
    }

    /** How it is shown: a blocked link that a person opened by hand counts as fine. */
    public function shownStatus(): string
    {
        return $this->status === 'blocked' && $this->manual_ok_at ? 'by_hand' : $this->status;
    }
}
