<?php

namespace App\Support;

/**
 * The verse blocks ([QURAN] ... [/QURAN]) of an article text, checked against the verified Quran text of the site (Quran, imported from Tanzil).
 * - A missing ARABIC line is filled in from the verified text. A missing TEXT line is filled in from the default translation.
 * - Arabic that is the same letters with other vowel marks is replaced by the verified Arabic. Arabic or English that really differs is NOT changed:
 *   it is a warning (a quotation is never reworded by a machine).
 * - When the English is the translation word for word, its name is put in the reference. The credit for the Arabic (Tanzil.net) is put there too.
 * - A verse that is named in the running text without a block is listed, so the writer can add it with the Verse helper.
 * Nothing happens when the Quran text has not been imported.
 */
class QuranBlocks
{
    /** The English counts as "the translation word for word" from this likeness (punctuation, capitals and brackets do not count). */
    public const SAME = 0.985;

    /** @return array{long: string, auto: list<string>, warnings: list<string>} */
    public static function check(string $long, ?string $translationCode = null): array
    {
        $out = ['long' => $long, 'auto' => [], 'warnings' => []];
        if (! Quran::available()) {
            return $out;
        }
        $t = Quran::translation($translationCode);
        $covered = [];
        $lines = explode("\n", str_replace("\r", '', $long));
        $result = [];
        $i = 0;
        while ($i < count($lines)) {
            if (! preg_match('/^\s*\[QURAN\]\s*$/i', $lines[$i])) {
                $result[] = $lines[$i++];

                continue;
            }
            $block = [];
            $j = $i + 1;
            while ($j < count($lines) && ! preg_match('/^\s*\[\/QURAN\]\s*$/i', $lines[$j])) {
                $block[] = $lines[$j++];
            }
            $closed = $j < count($lines);
            $fixed = static::fixBlock($block, $t, $covered, $out);
            $result[] = '[QURAN]';
            array_push($result, ...$fixed);
            if ($closed) {
                $result[] = '[/QURAN]';
            }
            $i = $j + 1;
        }
        $out['long'] = implode("\n", $result);

        // A verse that is named in the text but has no block.
        $outside = preg_replace('/^\s*\[(QURAN|HADITH)\].*?(?:^\s*\[\/\1\]\s*$|\z)/ims', '', $out['long']);
        $seen = 0;
        foreach (QuranRefs::find((string) $outside) as $q) {
            if (! $q['valid'] || isset($covered[$q['surah'].':'.$q['from']])) {
                continue;
            }
            if (++$seen > 8) {
                break;
            }
            $out['auto'][] = 'Surah '.Quran::surahName($q['surah']).' '.$q['ref'].' is named in the text without its verse. Add the verse with the Verse helper (Content > Verse helper), if it should be quoted.';
        }

        return $out;
    }

    /**
     * @param  list<string>  $lines
     * @param  array<string, true>  $covered
     * @param  array{long: string, auto: list<string>, warnings: list<string>}  $out
     * @return list<string> the lines of the block (ARABIC, TEXT, REF)
     */
    private static function fixBlock(array $lines, ?\App\Models\QuranTranslation $t, array &$covered, array &$out): array
    {
        $f = ['ARABIC' => '', 'TEXT' => '', 'REF' => ''];
        $key = null;
        foreach ($lines as $line) {
            if (preg_match('/^\s*(ARABIC|TEXT|REF)\s*:\s?(.*)$/iu', $line, $m)) {
                $key = strtoupper($m[1]);
                $f[$key] = trim($m[2]);
            } elseif ($key !== null && trim($line) !== '') {
                $f[$key] .= ' '.trim($line);
            }
        }

        $refs = Quran::parse($f['REF']);
        $ref = $refs[0] ?? null;
        if (! $ref || ! $ref['valid']) {
            return $lines;   // the checks of the AI import already say what is wrong with this reference
        }
        $official = Quran::arabic($ref['surah'], $ref['from'], $ref['to']);
        if ($official === null) {
            return static::lines($f);
        }
        for ($a = $ref['from']; $a <= $ref['to']; $a++) {
            $covered[$ref['surah'].':'.$a] = true;
        }
        $label = 'Surah '.Quran::surahName($ref['surah']).' '.$ref['ref'];
        $credit = false;
        $name = false;

        if ($f['ARABIC'] === '') {
            $f['ARABIC'] = $official;
            $credit = true;
            $out['auto'][] = 'The Arabic of '.$label.' was filled in from the verified Quran text (Tanzil). Check that it is the verse you mean.';
        } else {
            $same = Quran::compareArabic($f['ARABIC'], $official);
            if ($same === 'marks') {
                $f['ARABIC'] = $official;
                $credit = true;
                $out['auto'][] = 'The Arabic of '.$label.' had other vowel marks, so it was replaced by the verified Quran text (Tanzil). The letters are the same.';
            } elseif ($same === 'different') {
                $out['warnings'][] = 'The Arabic of '.$label.' is not the same as the verified Quran text. It was NOT changed: check it (the Verse helper shows the verified text).';
                $out['auto'][] = 'The Arabic of '.$label.' differs from the verified Quran text. Compare it in the Verse helper.';
            } else {
                $credit = true;
            }
        }

        if ($t) {
            $english = Quran::english($ref['surah'], $ref['from'], $ref['to'], $t);
            if ($english !== null) {
                if ($f['TEXT'] === '') {
                    $f['TEXT'] = $english;
                    $name = true;
                    $out['auto'][] = 'The English of '.$label.' was filled in from '.$t->name.'.';
                } else {
                    $like = Quran::similarity($f['TEXT'], $english);
                    if ($like >= self::SAME) {
                        $name = true;
                    } else {
                        $out['warnings'][] = 'The English of '.$label.' is not the '.$t->name.' wording ('.round($like * 100).'% alike). It was NOT changed: use the official words, or name the translator in the reference.';
                        $out['auto'][] = 'The English of '.$label.' differs from '.$t->name.' ('.round($like * 100).'% alike). Use the official wording, or name your own translation in REF.';
                    }
                }
            }
        }

        if ($name && $t && ! str_contains(mb_strtolower($f['REF']), mb_strtolower($t->name))) {
            $f['REF'] .= ' · '.$t->name;
        }
        if ($credit && ! str_contains(mb_strtolower($f['REF']), 'tanzil')) {
            $f['REF'] .= ' · '.Quran::ARABIC_CREDIT;
        }

        return static::lines($f);
    }

    /** @param array{ARABIC: string, TEXT: string, REF: string} $f */
    private static function lines(array $f): array
    {
        return array_values(array_filter([
            $f['ARABIC'] !== '' ? 'ARABIC: '.$f['ARABIC'] : null,
            $f['TEXT'] !== '' ? 'TEXT: '.$f['TEXT'] : null,
            $f['REF'] !== '' ? 'REF: '.$f['REF'] : null,
        ]));
    }
}
