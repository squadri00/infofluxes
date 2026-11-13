<?php

namespace App\Models;

use App\Support\Honorifics;
use Illuminate\Database\Eloquent\Model;

/** One of the 114 chapters of the Quran. */
class QuranChapter extends Model
{

    protected $guarded = [];

    public const COUNT = 114;

    /** The English name as shown to visitors: with its salutation when the chapter is named for a prophet. */
    public function getShownNameAttribute(): string
    {
        return trim($this->name.' '.($this->honorific ?? ''));
    }

    /** The meaning as shown to visitors ("Jonah (AS)"). A chapter without a salutation shows its meaning unchanged. */
    public function getShownMeaningAttribute(): string
    {
        return $this->honorific ? Honorifics::apply($this->meaning) : $this->meaning;
    }}
