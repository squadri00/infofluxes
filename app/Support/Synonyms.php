<?php

namespace App\Support;

use App\Models\SearchSynonym;
use Illuminate\Support\Facades\Cache;

/**
 * Turns what a visitor typed into what the search looks for. A word that is in a group of synonyms is looked for together with the other
 * words of the group (several-word terms too: "day of judgment"). Apostrophes are forgiven: qur'an, qur’an and quran all match.
 * A word the visitor typed is always searched exactly as typed. Words added from a group must be at least MIN_LETTERS long, so a short
 * spelling (esa, nuh) does not match half the words of the site.
 */
class Synonyms
{
    /** A word added from a group is skipped when it is shorter than this. */
    public const MIN_LETTERS = 4;

    /** The longest term, in words, that is looked for in what the visitor typed. */
    public const MAX_WORDS = 4;

    private const CACHE_KEY = 'search_synonyms_map_v1';

    /** One plain form: lower case, one kind of apostrophe, single spaces. */
    public static function plain(string $term): string
    {
        $term = mb_strtolower(trim($term));
        $term = str_replace(['’', '‘', 'ʿ', 'ʾ', '`', '´', 'ʻ', 'ʼ'], "'", $term);

        return trim(preg_replace('/\s+/u', ' ', $term));
    }

    /** The terms of one group, cleaned: plain, no empty ones, no repeats, no commas. @return list<string> */
    public static function cleanTerms(array $terms): array
    {
        $out = [];
        foreach ($terms as $term) {
            $plain = mb_substr(static::plain(str_replace(',', ' ', (string) $term)), 0, 60);
            if ($plain !== '' && ! in_array($plain, $out, true)) {
                $out[] = $plain;
            }
        }

        return $out;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * term => the number of its group, and group number => its terms. Only groups that are on.
     * Two groups that share a word are joined into one (a word that is in "Musa, Moosa" and in "Musa, Moses" finds all three).
     *
     * @return array{term: array<string, int>, group: array<int, list<string>>}
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $groups = [];
            foreach (SearchSynonym::query()->where('is_active', true)->orderBy('id')->get() as $row) {
                $terms = static::cleanTerms((array) $row->terms);
                if (count($terms) < 2) {
                    continue;
                }
                // join it with every group it shares a word with
                $joined = $terms;
                foreach ($groups as $key => $existing) {
                    if (array_intersect($existing, $joined) !== []) {
                        $joined = array_values(array_unique(array_merge($existing, $joined)));
                        unset($groups[$key]);
                    }
                }
                $groups[] = $joined;
            }

            $term = [];
            $group = [];
            foreach (array_values($groups) as $number => $terms) {
                $group[$number] = $terms;
                foreach ($terms as $t) {
                    $term[$t] = $number;
                }
            }

            return ['term' => $term, 'group' => $group];
        });
    }

    /**
     * What to look for, one entry for each thing the visitor typed: the other ways to find it.
     * Every entry is a list of words; a thing is found when any word of its list is found.
     *
     * @param  list<string>  $words  the words typed, in lower case
     * @return list<array{typed: string, look_for: list<string>, also: list<string>}>
     */
    public static function units(array $words): array
    {
        $map = static::map();
        $units = [];
        $count = count($words);
        for ($i = 0; $i < $count;) {
            for ($len = min(self::MAX_WORDS, $count - $i); $len >= 1; $len--) {
                $phrase = static::plain(implode(' ', array_slice($words, $i, $len)));
                if (isset($map['term'][$phrase])) {
                    $others = array_values(array_filter($map['group'][$map['term'][$phrase]], fn ($t) => $t !== $phrase && mb_strlen($t) >= self::MIN_LETTERS));
                    $units[] = ['typed' => implode(' ', array_slice($words, $i, $len)), 'look_for' => static::withApostrophes(array_merge([implode(' ', array_slice($words, $i, $len))], $others)), 'also' => $others];
                    $i += $len;

                    continue 2;
                }
            }
            $units[] = ['typed' => $words[$i], 'look_for' => static::withApostrophes([$words[$i]]), 'also' => []];
            $i++;
        }

        return $units;
    }

    /** Each word also as it is written with the other kinds of apostrophe, and with none. @return list<string> */
    private static function withApostrophes(array $words): array
    {
        $out = [];
        foreach ($words as $word) {
            $out[] = $word;
            if (preg_match("/['’]/u", $word)) {
                $straight = str_replace('’', "'", $word);
                $out[] = $straight;
                $out[] = str_replace("'", '’', $straight);
                $out[] = str_replace("'", '', $straight);
            }
        }

        return array_values(array_unique(array_filter($out, fn ($w) => $w !== '')));
    }

    /** What the search would also look for, for one phrase typed by a person. @return list<string> */
    public static function alsoFor(string $query): array
    {
        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($query)))));

        return collect(static::units($words))->pluck('also')->flatten()->unique()->values()->all();
    }
}
