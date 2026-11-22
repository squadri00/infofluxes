<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The score of one finished test. A guest's score has no name.
 * A signed-in member's score is kept with the account (My account > My scores) and goes when the account is deleted.
 */
class QuizAttempt extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $attempt) {
            $attempt->user_id ??= auth()->id();
        });
    }

    protected function casts(): array
    {
        return ['by_kind' => 'array', 'custom_data' => 'array'];
    }
}
