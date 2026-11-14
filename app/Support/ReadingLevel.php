<?php

namespace App\Support;

/**
 * A simple measure of how easy a text is to read: the average number of words in a sentence and the sentences that are too long.
 * The limits are in config/infofluxes.php (reading). It cannot judge hard words, only length, so a person must still read the text.
 */
class ReadingLevel
{
    /** Below this many words the measure means little. */
    public const MIN_WORDS = 60;

    /**
     * @return array{words: int, sentences: int, average: float, too_long: list<string>, hard: bool}
     */
    public static function measure(string $text): array
    {
        $text = preg_replace('/^\s*\[(QURAN|HADITH)\]\s*$.*?(?:^\s*\[\/\1\]\s*$|\z)/ims', '', str_replace("\r", '', $text)) ?? $text;
        $words = 0;
        $sentences = [];
        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            if (preg_match('/^\s*#{1,6}\s/', $line)) {
                continue; // a heading is not a sentence
            }
            $line = trim(preg_replace('/^\s*[-•*]\s+/u', '', $line));
            if ($line === '') {
                continue;
            }
            foreach (preg_split('/(?<=[.!?…])\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY) as $s) {
                $count = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', $s);
                if ($count > 0) {
                    $sentences[] = [trim($s), $count];
                    $words += $count;
                }
            }
        }
        $n = count($sentences);
        $average = $n > 0 ? round($words / $n, 1) : 0.0;
        $max = (int) config('infofluxes.reading.sentence_max', 35);
        $tooLong = array_values(array_map(fn ($x) => $x[0], array_filter($sentences, fn ($x) => $x[1] > $max)));

        return [
            'words' => $words, 'sentences' => $n, 'average' => $average, 'too_long' => $tooLong,
            'hard' => $words >= self::MIN_WORDS && ($average > (float) config('infofluxes.reading.avg_max', 18) || count($tooLong) > 0),
        ];
    }
}
