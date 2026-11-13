<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Someone who asked for the newsletter. */
class NewsletterSubscriber extends Model
{
    protected $guarded = [];

    protected $hidden = ['code_hash', 'unsubscribe_token'];

    public const STATUSES = [
        'pending' => 'Waiting for the code',
        'active' => 'Subscribed',
        'unsubscribed' => 'Unsubscribed',
    ];

    protected function casts(): array
    {
        return [
            'code_expires_at' => 'datetime', 'code_sent_at' => 'datetime', 'consent_at' => 'datetime',
            'confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime', 'custom_data' => 'array',
        ];
    }
}
