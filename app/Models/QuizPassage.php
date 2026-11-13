<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Arabic passage in the "Which one is from the Quran?" test. */
class QuizPassage extends Model
{

    protected $guarded = [];

    /** The kinds of source. The code words are fixed (the test logic uses them); the words shown are Labels. */
    public const KINDS = [
        'quran' => 'Quran',
        'hadith' => 'Hadith',
        'bible' => 'Arabic Bible',
        'poetry' => 'Arabic poetry',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_checked' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $passage) {
            $passage->words = max(1, count(preg_split('/\s+/u', trim((string) $passage->arabic), -1, PREG_SPLIT_NO_EMPTY)));
        });
    }

    /** How often readers picked this one when it was on the screen (for a wrong one: how good a disguise it is). */
    public function pickRate(): ?float
    {
        return $this->times_shown > 0 ? round($this->times_picked / $this->times_shown * 100, 1) : null;
    }
}
