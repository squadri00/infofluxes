<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One English passage in the test "Which scripture?". */
class ScripturePassage extends Model
{

    protected $guarded = [];

    /** The kinds. The code words are fixed (the test logic uses them); the words shown are Labels (scripture_kind_*). */
    public const KINDS = [
        'quran' => 'Quran',
        'hebrew_bible' => 'Hebrew Bible (Old Testament)',
        'new_testament' => 'New Testament',
        'gita' => 'Bhagavad Gita',
        'dhammapada' => 'Dhammapada',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_checked' => 'boolean'];
    }
}
