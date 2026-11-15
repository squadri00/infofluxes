<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Synonyms;
use Illuminate\Database\Eloquent\Model;

/** A group of words that the site search treats as the same (Quran, Qur'an, Koran). */
class SearchSynonym extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['terms' => 'array', 'is_active' => 'boolean', 'custom_data' => 'array'];
    }

    protected static function booted(): void
    {
        // The words are kept in a plain form, and what the search remembers is thrown away when a group changes.
        static::saving(function (self $s) {
            $s->terms = Synonyms::cleanTerms((array) $s->terms);
        });
        static::saved(fn () => Synonyms::forget());
        static::deleted(fn () => Synonyms::forget());
    }

    public function activityLabel(): string
    {
        return implode(', ', array_slice((array) $this->terms, 0, 6)).(count((array) $this->terms) > 6 ? ', …' : '');
    }

    /** Puts the starting list in, once: only when there are no groups yet. A group someone changed or removed is never put back. */
    public static function installDefaults(): int
    {
        if (static::query()->exists()) {
            return 0;
        }
        $data = require resource_path('data/search-synonyms.php');
        $made = 0;
        foreach ($data['active'] as $terms) {
            static::create(['terms' => $terms, 'is_active' => true, 'note' => null]);
            $made++;
        }
        foreach ($data['inactive'] as $note => $groups) {
            foreach ($groups as $terms) {
                static::create(['terms' => $terms, 'is_active' => false, 'note' => $note]);
                $made++;
            }
        }

        return $made;
    }
}
