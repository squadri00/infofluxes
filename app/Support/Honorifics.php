<?php

namespace App\Support;

/**
 * The salutations that follow a name, as the site's rulebook (facts/STYLE.md) asks:
 *
 *   Prophet Muhammad                 ﷺ   (Muhammad ﷺ)
 *   every other prophet              (AS)
 *   companions and the Prophet's wives   (RA)
 *   Allah                            (SWT)
 *   the Prophet's family (Ahl al-Bayt)   (AS), but the people your table names as companions follow (RA)
 *
 * Every time the name is written, in the site's own words. Names inside quotation marks (a quotation, a cited title)
 * are left exactly as they are. This class FINDS names with no salutation and can ADD the missing ones; it never
 * removes anything a person wrote, except that an old "(PBUH)" is changed to the right salutation.
 *
 * The lists are the usual names in the Quran and in the Muslim tradition. A name that is also an ordinary word is
 * only matched when it is written with a capital letter. A person should still read the result.
 */
class Honorifics
{
    public const MUHAMMAD = 'ﷺ';

    public const PROPHET = '(AS)';

    public const COMPANION = '(RA)';

    public const ALLAH = '(SWT)';

    /** The prophets named in the Quran, with the spellings the site uses (the Arabic forms and the English ones). */
    public const PROPHETS = [
        'Adam', 'Idris', 'Nuh', 'Noah', 'Hud', 'Salih', 'Ibrahim', 'Abraham', 'Lut', 'Lot',
        'Ismail', "Isma'il", 'Ismael', 'Ishmael', 'Ishaq', 'Isaac', 'Yaqub', "Ya'qub", 'Jacob',
        'Yusuf', 'Joseph', 'Ayyub', 'Job', 'Shuayb', "Shu'ayb", 'Musa', 'Moses', 'Harun', 'Aaron',
        "Dhu'l-Kifl", 'Dhul-Kifl', 'Dhul Kifl', 'Dawud', 'David', 'Sulayman', 'Solomon', 'Ilyas', 'Elijah',
        'Al-Yasa', "Al-Yasa'", 'Elisha', 'Yunus', 'Jonah', 'Zakariya', 'Zechariah', 'Yahya',
        'Jesus Christ', 'Jesus', 'Isa',
        // Not prophets, but they receive (AS) as well, by the site owner's decision: Maryam (Mary) and Hawwa (Eve)
        'Maryam', 'Mary', 'Hawwa', 'Hawa', 'Eve',
    ];

    public const MUHAMMAD_NAMES = ['Muhammad', 'Mohammed', 'Mohammad', 'Muhammed'];

    /** Companions, the Prophet's wives, and the family members your table lists with (RA). */
    public const COMPANIONS = [
        'Abu Bakr', 'Umar ibn al-Khattab', 'Umar', 'Uthman ibn Affan', 'Uthman', 'Ali ibn Abi Talib',
        'Aisha', "A'isha", 'Khadijah', 'Khadija', 'Fatimah', 'Fatima', 'Hafsah', 'Hafsa',
        'Hasan', 'Husayn', 'Ibn Abbas', "Ibn Mas'ud", 'Abu Hurayrah', 'Abu Hurairah', 'Bilal', 'Zayd ibn Thabit',
    ];

    /** Salutations that may already be there (so none is added twice). */
    private const EXISTING = '\((?:AS|PBUH|SAW|RA|SWT)\)|ﷺ|\(peace be upon him\)|\(may Allah be pleased with (?:him|her|them)\)';

    /** @return array<string, string> lower-case name => salutation */
    private static function map(): array
    {
        $map = [];
        foreach (self::PROPHETS as $n) {
            $map[mb_strtolower($n)] = self::PROPHET;
        }
        $map['allah'] = self::ALLAH;
        foreach (self::MUHAMMAD_NAMES as $n) {
            $map[mb_strtolower($n)] = self::MUHAMMAD;
        }
        foreach (self::COMPANIONS as $n) {
            $map[mb_strtolower($n)] = self::COMPANION;
        }

        return $map;
    }

    private static function pattern(): string
    {
        $names = array_merge(self::PROPHETS, self::MUHAMMAD_NAMES, self::COMPANIONS, ['Allah']);
        usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        // A name that is the start of a longer name is left alone: "Muhammad Iqbal", "Muhammad Asad" (a capital letter follows on the same line),
        // "Hasan al-Basri", "Hasan bin ...".
        $alt = implode('|', array_map(function ($n) {
            $q = preg_quote($n, '/');
            if (in_array($n, self::MUHAMMAD_NAMES, true)) {
                return $q.'(?![ 	]+(?!(?:and|or)\b)\p{Lu})';
            }

            if ($n === 'Allah') {
                return '(?<!may )'.$q;                         // "(may Allah be pleased with him)" is a salutation already
            }

            if ($n === 'Mary') {
                return $q.'(?!\s+Magdalene)';                  // Mary Magdalene is not Maryam
            }

            if ($n === 'Eve') {
                return $q.'(?!\s+of\b)';                       // "on the eve of the battle" is not a name
            }

            // "Hasan" is also a word: the Hadith grade ("Sahih, Hasan and Daif"), and "Qard Hasan" (a good loan). Those are not the Companion.
            return $n === 'Hasan' ? '(?<!Qard )(?<!Sahih, )(?<!Sahih and )'.$q.'(?!\s+(?:al-|b\.|bin\b|and\s+Da))' : $q;
        }, $names));
        $prophets = implode('|', array_map(fn ($n) => preg_quote($n, '/'), self::PROPHETS));

        // name, then an optional 's, then an optional gloss with the other name, "(Jesus)" or "(Moses, peace be upon him)", then an existing salutation
        return '/(?<![\p{L}\p{N}\'’-])(?P<name>'.$alt.')(?![\p{L}\p{N}])(?P<poss>[\'’]s|[\'’](?![\p{L}\p{N}]))?(?P<gloss>\s*\((?!(?:AS|RA|SWT|SAW|PBUH)\))\p{Lu}[\p{L}\'’-]*(?:\s\p{Lu}[\p{L}\'’-]*){0,2}(?:,\s*(?:peace be upon him|AS|RA|SWT))?\))?(?P<sal>\s*(?:'.self::EXISTING.'))?/u';
    }

    /**
     * Walk through the text outside quotation marks, and call $handle for each name found.
     *
     * @param  callable(array<string, string>, string): string  $handle  receives the match and the right salutation, returns the new text
     */
    private static function walk(string $text, callable $handle): string
    {
        if (Text::isFormatted($text)) {
            $pieces = preg_split('/(<[^>]*>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
            foreach ($pieces as $i => $piece) {
                if ($i % 2 === 0 && $piece !== '') {
                    $pieces[$i] = static::walkWords($piece, $handle);
                }
            }

            return implode('', $pieces);
        }

        return static::walkWords($text, $handle);
    }

    private static function walkWords(string $text, callable $handle): string
    {
        $map = self::map();
        $pattern = self::pattern();

        // Quoted parts ("..." or “...”) are never touched: they are what somebody else wrote.
        $parts = preg_split('/("[^"]*"|“[^”]*”)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                continue;
            }

            $parts[$i] = preg_replace_callback($pattern, function (array $m) use ($map, $handle) {
                $key = mb_strtolower($m['name']);
                $salutation = $map[$key] ?? null;
                if ($salutation === null) {
                    return $m[0];
                }

                if (stripos((string) ($m['gloss'] ?? ''), 'peace be upon him') !== false && ($m['sal'] ?? '') === '') {
                    $m['sal'] = ' (peace be upon him)';              // already said inside the bracket
                } elseif (($m['sal'] ?? '') === '' && preg_match('/,\s*(AS|RA|SWT)\)$/', (string) ($m['gloss'] ?? ''), $g)) {
                    $m['sal'] = ' ('.$g[1].')';                      // "Isa (Jesus, AS)": the salutation is inside the bracket
                }

                return $handle($m, $salutation);
            }, $part) ?? $part;
        }

        return implode('', $parts);
    }

    /**
     * Names that have no salutation yet.
     *
     * @return string[] each as "Musa (needs (AS))"
     */
    public static function missing(string $text): array
    {
        $found = [];
        self::walk($text, function (array $m, string $salutation) use (&$found) {
            $has = trim((string) ($m['sal'] ?? ''));
            $ok = $has !== '' && ! preg_match('/PBUH|SAW/', $has);
            if (! $ok) {
                $found[] = $m['name'].' (needs '.$salutation.')';
            }

            return $m[0];
        });

        return $found;
    }

    /** The same text with every missing salutation added, and an old "(PBUH)" put right. Safe to run again. */
    public static function apply(string $text): string
    {
        return self::walk($text, function (array $m, string $salutation) {
            $has = trim((string) ($m['sal'] ?? ''));
            if ($has !== '' && ! preg_match('/PBUH|SAW/', $has)) {
                return $m[0];                                       // already right
            }

            $before = $m['name'].($m['poss'] ?? '').($m['gloss'] ?? '');

            return $before.' '.$salutation;
        });
    }
}
