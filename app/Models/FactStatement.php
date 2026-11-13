<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One statement in the test "Fact or myth?". */
class FactStatement extends Model
{

    protected $guarded = [];

    /** What the statement is about (for your own sorting). */
    public const TOPICS = [
        'scripture' => 'Scripture (what a text says)',
        'history' => 'History',
        'belief' => 'Belief and practice',
        'language' => 'Language and words',
    ];

    protected function casts(): array
    {
        return ['is_true' => 'boolean', 'is_active' => 'boolean', 'is_checked' => 'boolean'];
    }
}
