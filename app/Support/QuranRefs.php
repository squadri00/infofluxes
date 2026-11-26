<?php

namespace App\Support;

/**
 * Finds Quran references such as "Quran 2:255", "Surah Al-Baqarah 2:255-257" or "(112:1)" in a text
 * and says which of them cannot exist (a surah above 114, or a verse above the number of verses of the surah).
 * It checks only that the number exists. It does not check that the verse says what the article says: a person must do that.
 */
class QuranRefs
{
    /** @return list<int> verses of each surah, index 0 = surah 1 */
    public static function counts(): array
    {
        static $counts = null;

        return $counts ??= require resource_path('data/quran-verse-counts.php');
    }

    /**
     * @return list<array{ref: string, surah: int, from: int, to: int, valid: bool, problem: ?string}>
     */
    public static function find(string $text): array
    {
        $found = [];
        // 1. a Quran word, then (within a few words) surah:verse
        $named = '/(?:Qur[\'’]?an|Quran|Koran|Sura[h]?|Surat|\bQ\.?)(?![A-Za-z])[^0-9\n]{0,40}?(\d{1,3})\s*:\s*(\d{1,3})(?:\s*[-–]\s*(\d{1,3}))?/iu';
        // 2. a bare number pair in brackets: (2:255) or [112:1-4]
        $bare = '/[(\[]\s*(\d{1,3})\s*:\s*(\d{1,3})(?:\s*[-–]\s*(\d{1,3}))?\s*[)\]]/u';
        foreach ([$named, $bare] as $pattern) {
            if (preg_match_all($pattern, $text, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $surah = (int) $hit[1];
                    $from = (int) $hit[2];
                    $to = isset($hit[3]) && $hit[3] !== '' ? (int) $hit[3] : $from;
                    $key = $surah.':'.$from.'-'.$to;
                    $found[$key] ??= static::judge($surah, $from, $to);
                }
            }
        }

        return array_values($found);
    }

    /** @return array{ref: string, surah: int, from: int, to: int, valid: bool, problem: ?string} */
    public static function judge(int $surah, int $from, int $to): array
    {
        $counts = static::counts();
        $ref = $surah.':'.$from.($to !== $from ? '-'.$to : '');
        $problem = null;
        if ($surah < 1 || $surah > count($counts)) {
            $problem = 'there is no surah '.$surah.' (the Quran has '.count($counts).' surahs)';
        } elseif ($from < 1 || $to < $from) {
            $problem = 'the verse numbers are not in order';
        } elseif ($to > $counts[$surah - 1]) {
            $problem = 'surah '.$surah.' has only '.$counts[$surah - 1].' verses';
        }

        return ['ref' => $ref, 'surah' => $surah, 'from' => $from, 'to' => $to, 'valid' => $problem === null, 'problem' => $problem];
    }
}
