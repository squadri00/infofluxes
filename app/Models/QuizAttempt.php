<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The score of one finished test. Nothing about the person is kept. */
class QuizAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['by_kind' => 'array'];
    }
}
