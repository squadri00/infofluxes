<?php

namespace App\Support\StoryMaker;

use App\Models\Story;

/**
 * Reads the answer of the AI for step 2 (the translations of a saved story) and checks every language on its own.
 * A language with a problem is not saved; the other languages can still be saved. It saves nothing.
 */
class TranslationReader
{
    /**
     * @param  list<string>  $codes  the languages that were asked for
     * @return array{languages: array<string, array{errors: list<string>, warnings: list<string>, data: array<string, mixed>}>, notes: list<string>, missing: list<string>}
     */
    public static function read(string $answer, Story $story, array $codes): array
    {
        $languages = Rules::languages();
        $source = static::sourceOf($story);
        $notes = [];
        $out = [];

        $blocks = static::blocks($answer, $languages);
        foreach ($blocks as $code => $text) {
            if (! in_array($code, $codes, true)) {
                $notes[] = ($languages[$code]['name'] ?? $code).' was not asked for. It is left out.';

                continue;
            }
            $out[$code] = static::check($code, Reader::parse($text), $source, $languages[$code]);
        }
        $missing = array_values(array_diff($codes, array_keys($out)));
        foreach ($missing as $code) {
            $notes[] = ($languages[$code]['name'] ?? $code).' is not in the answer.';
        }

        return ['languages' => $out, 'notes' => $notes, 'missing' => $missing];
    }

    /** The answer cut at the "LANG: xx" lines. A language name (Urdu) is understood as its code. @return array<string, string> */
    private static function blocks(string $answer, array $languages): array
    {
        $byName = [];
        foreach ($languages as $code => $l) {
            $byName[mb_strtolower($l['name'])] = $code;
        }
        $blocks = [];
        $current = null;
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $answer)) as $line) {
            if (preg_match('/^\s*```/', $line) || preg_match('/^\s*={3,}\s*$/', $line)) {
                continue;
            }
            if (preg_match('/^\s*(?:\*\*|__)?LANG(?:UAGE)?(?:\*\*|__)?\s*:\s*(?:\*\*|__)?\s*([A-Za-z\-]{2,12})/u', $line, $m)) {
                $word = mb_strtolower($m[1]);
                $current = isset($languages[$word]) ? $word : ($byName[$word] ?? $word);
                $blocks[$current] = '';

                continue;
            }
            if (preg_match('/^\s*(?:\*\*|__)?END(?:\*\*|__)?\s*$/i', $line)) {
                $current = null;

                continue;
            }
            if ($current !== null) {
                $blocks[$current] .= $line."\n";
            }
        }

        return $blocks;
    }

    /** The English story as chapters and slides, with the parts that must stay as they are. */
    private static function sourceOf(Story $story): array
    {
        $en = config('infofluxes.default_locale');
        $story->loadMissing('chapters.slides');
        $chapters = [];
        foreach ($story->chapters as $chapter) {
            $slides = [];
            foreach ($chapter->slides as $slide) {
                $slides[] = [
                    'heading' => (string) $slide->getTranslation('heading', $en, false),
                    'body' => (string) $slide->getTranslation('body', $en, false),
                ];
            }
            $chapters[] = ['slides' => $slides];
        }

        return ['chapters' => $chapters, 'short' => (string) $story->getTranslation('short_detail', $en, false)];
    }

    /** @return array{errors: list<string>, warnings: list<string>, data: array<string, mixed>} */
    private static function check(string $code, array $f, array $source, array $language): array
    {
        $errors = [];
        $warnings = [];
        $name = $language['name'];
        $chapters = $f['chapters'];

        $title = trim($f['TITLE'] ?? '');
        $short = trim($f['SHORT'] ?? '');
        if ($title === '') {
            $errors[] = 'TITLE is missing.';
        } elseif (mb_strlen($title) > 160) {
            $errors[] = 'TITLE is longer than 160 letters.';
        }
        if ($short === '') {
            $warnings[] = 'SHORT is missing.';
        } elseif (mb_strlen($short) > 500) {
            $errors[] = 'SHORT is longer than 500 letters.';
        }

        // The structure must be the same as the English: the pictures are shared.
        if (count($chapters) !== count($source['chapters'])) {
            $errors[] = 'The answer has '.count($chapters).' chapter(s). The English story has '.count($source['chapters']).'. The structure must be the same.';
        } else {
            foreach ($source['chapters'] as $ci => $c) {
                if (count($chapters[$ci]['slides']) !== count($c['slides'])) {
                    $errors[] = 'Chapter '.($ci + 1).' has '.count($chapters[$ci]['slides']).' slide(s). The English chapter has '.count($c['slides']).'. The slides must be the same.';
                }
            }
        }

        if (count($chapters) === count($source['chapters'])) {
            $n = 0;
            foreach ($source['chapters'] as $ci => $c) {
                foreach ($c['slides'] as $si => $src) {
                    $n++;
                    $slide = $chapters[$ci]['slides'][$si] ?? null;
                    if (! $slide) {
                        continue;
                    }
                    $heading = trim($slide['heading']);
                    $body = trim($slide['body']);
                    if ($heading === '') {
                        $errors[] = 'Slide '.$n.' has no HEADING.';
                    }
                    if ($body === '') {
                        $errors[] = 'Slide '.$n.' has no BODY.';

                        continue;
                    }
                    if (trim($slide['alt']) === '') {
                        $warnings[] = 'Slide '.$n.' has no ALT line.';
                    }
                    static::checkBody($n, $src['body'], $body, $language, $errors, $warnings);
                }
            }
        }

        $text = $title."\n".$short."\n".implode("\n", array_map(fn ($c) => implode("\n", array_map(fn ($s) => $s['heading']."\n".$s['body'], $c['slides'])), $chapters));
        if (preg_match('~https?://|www\.~i', $text)) {
            $warnings[] = 'The text has a web address. A story does not have web addresses in its slides.';
        }
        if (strip_tags($text) !== $text) {
            $warnings[] = 'The text has "<" and ">" signs. They are shown as plain text, not as formatting.';
        }
        $saluteSource = mb_substr_count($source['short'], 'ﷺ') + array_sum(array_map(fn ($c) => array_sum(array_map(fn ($s) => mb_substr_count($s['body'], 'ﷺ'), $c['slides'])), $source['chapters']));
        if ($saluteSource > mb_substr_count($text, 'ﷺ')) {
            $warnings[] = 'The symbol ﷺ is in the English story '.$saluteSource.' time(s) and in this translation '.mb_substr_count($text, 'ﷺ').' time(s). Check the salutations.';
        }

        $keywords = collect(explode(',', str_replace(['،', '、'], ',', (string) ($f['KEYWORDS'] ?? ''))))->map(fn ($w) => trim(mb_substr(trim($w), 0, 60)))->filter()->unique()->take(20)->values()->all();

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'data' => [
                'title' => $title, 'short' => $short, 'keywords' => $keywords,
                'meta_title' => static::oneLine($f['META TITLE'] ?? '') ?: null,
                'meta_description' => static::oneLine($f['META DESCRIPTION'] ?? '') ?: null,
                'chapters' => array_map(fn ($c) => ['title' => $c['title'], 'slides' => array_map(fn ($s) => ['heading' => $s['heading'], 'body' => $s['body'], 'alt' => $s['alt']], $c['slides'])], $chapters),
            ],
        ];
    }

    /** One slide: the Arabic lines and the verse numbers must be kept, and the words must really be in the language. */
    private static function checkBody(int $n, string $source, string $body, array $language, array &$errors, array &$warnings): void
    {
        $norm = fn (string $t) => trim(preg_replace('/\s+/u', ' ', $t));

        // Every Arabic line (a verse or a saying) of the English slide must be in the translation, letter for letter.
        $bodyLines = array_map($norm, preg_split('/\n+/', $body));   // a verse must be a line of its own, exactly as it was
        $arabic = [];
        foreach (preg_split('/\n+/', $source) as $line) {
            if (Arabic::isLine($line)) {
                $arabic[] = $norm($line);
            }
            if (Arabic::isLine($line) && ! in_array($norm($line), $bodyLines, true)) {
                $errors[] = 'Slide '.$n.': the Arabic line "'.mb_substr($norm($line), 0, 40).'…" was changed or left out. A quotation is never changed.';
            }
        }
        // The verse numbers (112:1) must be the same.
        preg_match_all('/\d{1,3}\s*:\s*\d{1,3}(?:\s*[-–]\s*\d{1,3})?/u', $source, $a);
        preg_match_all('/\d{1,3}\s*:\s*\d{1,3}(?:\s*[-–]\s*\d{1,3})?/u', $body, $b);
        $normNums = fn (array $list) => array_map(fn ($x) => preg_replace('/\s+/', '', $x), $list[0]);
        if (array_diff($normNums($a), $normNums($b)) !== []) {
            $warnings[] = 'Slide '.$n.': a verse reference of the English slide is missing or different (check the numbers).';
        }

        // The words must be in the language (not the English left as it was).
        $prose = static::prose($body, $arabic);
        $srcProse = static::prose($source, $arabic);
        if ($language['rtl'] || in_array($language['name'], ['Hindi'], true)) {
            $letters = preg_match_all('/\p{L}/u', $prose);
            $latin = preg_match_all('/\p{Latin}/u', $prose);
            if ($letters > 10 && $latin / max(1, $letters) > 0.5) {
                $warnings[] = 'Slide '.$n.' looks like it is still in English or another script, not in '.$language['name'].'.';
            }
        } elseif ($norm($prose) !== '' && $norm($prose) === $norm($srcProse)) {
            $warnings[] = 'Slide '.$n.' is the same as the English. It was not translated.';
        }

        // About as long as the English.
        $wTr = count(preg_split('/\s+/u', trim($prose), -1, PREG_SPLIT_NO_EMPTY));
        $wSr = count(preg_split('/\s+/u', trim($srcProse), -1, PREG_SPLIT_NO_EMPTY));
        if ($wSr >= 15 && ($wTr < $wSr * 0.45 || $wTr > $wSr * 2.4)) {
            $warnings[] = 'Slide '.$n.' has '.$wTr.' words and the English has '.$wSr.'. Something may be missing or added.';
        }
    }

    /** The told words, without the verse lines (the Arabic lines of the English slide) and the reference lines in brackets. */
    private static function prose(string $body, array $verseLines): string
    {
        $keep = [];
        foreach (preg_split('/\n+/', $body) as $line) {
            $norm = trim(preg_replace('/\s+/u', ' ', $line));
            if (in_array($norm, $verseLines, true)) {
                continue;
            }
            if (preg_match('/^\s*[(\[].*[)\]]\s*$/u', $line) && preg_match('/\d/', $line)) {
                continue;
            }
            $keep[] = $line;
        }

        return trim(implode(' ', $keep));
    }
    private static function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    }
}
