<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One quote in the test "Who said it?". */
class QuotePassage extends Model
{

    protected $guarded = [];

    /** The traditions. The code words are fixed (the test logic uses them); the words shown are Labels (who_kind_*). */
    public const KINDS = [
        'muslim' => 'Muslim',
        'christian' => 'Christian',
        'jewish' => 'Jewish',
        'hindu' => 'Hindu',
        'buddhist' => 'Buddhist',
        'secular' => 'Secular, sceptic or atheist',
    ];

    protected function casts(): array
    {
        return ['is_attributed' => 'boolean', 'is_active' => 'boolean', 'is_checked' => 'boolean'];
    }
}
