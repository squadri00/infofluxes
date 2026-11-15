<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Explainers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A word note: a short, plain explanation of a word, a person, a place or an event. Code E-<number>.
 * In an article it is called by writing @[the word]; a reader taps the word and sees the note. Only a published note is shown.
 */
class Explainer extends Model
{
    use HasTranslations, LogsActivity, SoftDeletes;

    public const KINDS = ['word' => 'Word', 'person' => 'Person', 'place' => 'Place', 'event' => 'Event', 'idea' => 'Idea'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    public array $translatable = ['term', 'body'];

    /** Besides the translated texts, these may be put back from the history. */
    public array $activityUndo = ['source_line', 'aliases'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aliases' => 'array', 'ai_assisted' => 'boolean', 'custom_data' => 'array'];
    }

    protected static function booted(): void
    {
        // The words of the explainers are remembered by the site. A change must be seen at once.
        static::saved(fn () => Explainers::forget());
        static::deleted(fn () => Explainers::forget());
        static::restored(fn () => Explainers::forget());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getCodeAttribute(): string
    {
        return 'E-'.$this->id;
    }

    /** The word in the main language. */
    public function mainTerm(): string
    {
        $locale = config('infofluxes.default_locale');

        return (string) ($this->getTranslation('term', $locale, false) ?: $this->getTranslation('term', $locale));
    }

    public function activityLabel(): string
    {
        return $this->code.' '.$this->mainTerm();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** The words this note answers to: the word itself and its other spellings, in a plain form. @return list<string> */
    public function lookupWords(): array
    {
        return \App\Support\Synonyms::cleanTerms(array_merge([$this->mainTerm()], (array) $this->aliases));
    }
}
