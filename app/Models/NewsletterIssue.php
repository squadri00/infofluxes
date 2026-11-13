<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One issue of the newsletter. A draft can be changed; once sending has started it is locked. */
class NewsletterIssue extends Model
{
    protected $guarded = [];

    public const STATUSES = ['draft' => 'Draft', 'sending' => 'Sending', 'sent' => 'Sent'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'custom_data' => 'array'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NewsletterDelivery::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function pendingCount(): int
    {
        return $this->deliveries()->where('status', 'pending')->count();
    }
}
