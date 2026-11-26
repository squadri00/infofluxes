<?php

namespace App\Support;

use App\Models\QuranTranslation;
use App\Models\QuranTranslationVerse;
use App\Models\QuranVerse;
use Illuminate\Support\Collection;

/**
 * The Quran text of the site (Arabic from the verified Tanzil text, translations as they were imported): finds verses by reference, makes the
 * [QURAN] blocks of an article, and compares the Arabic and the English that a text carries with the official words.
 * The text is never changed here: it is read from the database as it was imported.
 */
class Quran
{
    /** The credit that goes with the Arabic (the license of the Tanzil text asks for the source and a link to tanzil.net). */
    public const ARABIC_CREDIT = 'Arabic text: Tanzil.net';

    private static ?bool $has = null;

    public static function forget(): void
    {
        static::$has = null;
    }

    /** Is the Arabic text imported? */
    public static function available(): bool
    {
        return static::$has ??= QuranVerse::query()->exists();
    }

    public static function surahName(int $surah): string
    {
        $names = require resource_path('data/quran-surah-names.php');

        return $names[$surah - 1] ?? ('Surah '.$surah);
    }

    /** @return Collection<int, QuranTranslation> */
    public static function translations(): Collection
    {
        return QuranTranslation::query()->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->get();
    }

    /** The translation with this code, or else the default one, or else the first. */
    public static function translation(?string $code = null): ?QuranTranslation
    {
        $all = static::translations();

        return ($code ? $all->firstWhere('code', $code) : null) ?? $all->first();
    }

    /**
     * The verse references in a text, one entry for each different one: "30:30", "Surah Ar-Rum 30:30", "52:35-36", "(2:255)".
     *
     * @return list<array{ref: string, surah: int, from: int, to: int, valid: bool, problem: ?string}>
     */
    public static function parse(string $text): array
    {
        $found = [];
        if (preg_match_all('/(?<![\d:])(\d{1,3})\s*:\s*(\d{1,3})(?:\s*[-\x{2013}\x{2014}]\s*(\d{1,3}))?(?![\d:])/u', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $surah = (int) $hit[1];
                $from = (int) $hit[2];
                $to = isset($hit[3]) && $hit[3] !== '' ? (int) $hit[3] : $from;
                $judged = QuranRefs::judge($surah, $from, $to);
                $found[$judged['ref']] ??= $judged;
            }
        }

        return array_values($found);
    }

    /** The Arabic of the verses from..to of a surah, joined with a space; null when a verse is missing. */
    public static function arabic(int $surah, int $from, int $to): ?string
    {
        $rows = QuranVerse::query()->where('surah', $surah)->whereBetween('ayah', [$from, $to])->orderBy('ayah')->pluck('arabic');

        return $rows->count() === ($to - $from + 1) ? $rows->implode(' ') : null;
    }

    public static function english(int $surah, int $from, int $to, ?QuranTranslation $translation): ?string
    {
        if (! $translation) {
            return null;
        }
        $rows = QuranTranslationVerse::query()->where('translation_id', $translation->id)->where('surah', $surah)->whereBetween('ayah', [$from, $to])->orderBy('ayah')->pluck('text');

        return $rows->count() === ($to - $from + 1) ? $rows->implode(' ') : null;
    }

    /** "Surah Ar-Rum 30:30 · Saheeh International · Arabic text: Tanzil.net" */
    public static function ref(int $surah, int $from, int $to, ?QuranTranslation $translation = null, bool $arabicCredit = true): string
    {
        $parts = ['Surah '.static::surahName($surah).' '.$surah.':'.$from.($to !== $from ? '-'.$to : '')];
        if ($translation) {
            $parts[] = $translation->name;
        }
        if ($arabicCredit) {
            $parts[] = self::ARABIC_CREDIT;
        }

        return implode(' · ', $parts);
    }

    /**
     * The [QURAN] block of an article for these verses, or null when a verse is missing.
     *
     * @param  array{surah: int, from: int, to: int}  $ref
     */
    public static function block(array $ref, ?QuranTranslation $translation = null): ?string
    {
        $arabic = static::arabic($ref['surah'], $ref['from'], $ref['to']);
        if ($arabic === null) {
            return null;
        }
        $english = static::english($ref['surah'], $ref['from'], $ref['to'], $translation);
        $lines = ['[QURAN]', 'ARABIC: '.$arabic];
        if ($english !== null) {
            $lines[] = 'TEXT: '.$english;
        }
        $lines[] = 'REF: '.static::ref($ref['surah'], $ref['from'], $ref['to'], $english !== null ? $translation : null);
        $lines[] = '[/QURAN]';

        return implode("\n", $lines);
    }

    /** The Arabic without vowel marks, small signs and the stretching line, and with the forms of alif, ya and ha made equal: for comparing only. */
    public static function normalizeArabic(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}\x{08D3}-\x{08FF}]/u', '', $text);
        $text = strtr($text, ["\u{0623}" => "\u{0627}", "\u{0625}" => "\u{0627}", "\u{0622}" => "\u{0627}", "\u{0671}" => "\u{0627}", "\u{0649}" => "\u{064A}", "\u{06CC}" => "\u{064A}", "\u{0629}" => "\u{0647}"]);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** 'exact' (the same), 'marks' (the same letters, other vowel marks) or 'different'. */
    public static function compareArabic(string $given, string $official): string
    {
        $clean = fn ($t) => trim(preg_replace('/\s+/u', ' ', $t));
        if ($clean($given) === $clean($official)) {
            return 'exact';
        }

        return static::normalizeArabic($given) === static::normalizeArabic($official) ? 'marks' : 'different';
    }

    /** How much alike two English texts are, 0 to 1, by their words in order (punctuation, capitals and brackets do not count). */
    public static function similarity(string $a, string $b): float
    {
        $words = fn ($t) => preg_split('/\s+/u', trim(preg_replace('/[^\p{L}\p{N}\x{2019}\']+/u', ' ', mb_strtolower(str_replace('’', "'", $t)))), -1, PREG_SPLIT_NO_EMPTY);
        $x = $words($a);
        $y = $words($b);
        if ($x === [] || $y === []) {
            return 0.0;
        }
        $prev = array_fill(0, count($y) + 1, 0);
        foreach ($x as $xi) {
            $row = [0];
            foreach ($y as $j => $yj) {
                $row[$j + 1] = $xi === $yj ? $prev[$j] + 1 : max($prev[$j + 1], $row[$j]);
            }
            $prev = $row;
        }

        return 2 * $prev[count($y)] / (count($x) + count($y));
    }
}
