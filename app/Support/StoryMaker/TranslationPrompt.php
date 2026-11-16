<?php

namespace App\Support\StoryMaker;

use App\Models\Story;

/** Makes the prompt for step 2 of the Story maker: the saved English story, to be translated into the chosen languages. */
class TranslationPrompt
{
    /** The English story in the shape the answer will have (without the picture lines). */
    public static function source(Story $story): string
    {
        $en = config('infofluxes.default_locale');
        $story->loadMissing('chapters.slides');
        $text = fn (?string $v) => trim((string) $v);

        $out = 'TITLE: '.$text($story->getTranslation('title', $en, false))."\n";
        $out .= 'SHORT: '.$text($story->getTranslation('short_detail', $en, false))."\n";
        $n = 0;
        foreach ($story->chapters as $chapter) {
            $out .= 'CHAPTER: '.$text($chapter->getTranslation('title', $en, false))."\n";
            foreach ($chapter->slides as $slide) {
                $n++;
                $out .= "SLIDE {$n}\n";
                $out .= 'HEADING: '.$text($slide->getTranslation('heading', $en, false))."\n";
                $out .= 'BODY: '.$text($slide->getTranslation('body', $en, false))."\n";
                $out .= 'ALT: '.$text($slide->getTranslation('image_alt', $en, false))."\n";
            }
        }
        $out .= 'KEYWORDS: '.implode(', ', (array) $story->search_words)."\n";
        $out .= 'META TITLE: '.$text($story->getTranslation('meta_title', $en, false))."\n";
        $out .= 'META DESCRIPTION: '.$text($story->getTranslation('meta_description', $en, false))."\n";

        return $out;
    }

    /**
     * @param  list<string>  $codes  the languages to write, from Rules::languages()
     */
    public static function build(Story $story, array $codes): string
    {
        $languages = Rules::languages();
        $codes = array_values(array_filter($codes, fn ($c) => isset($languages[$c])));
        $story->loadMissing('chapters.slides');
        $chapters = $story->chapters->count();
        $slides = $story->chapters->sum(fn ($c) => $c->slides->count());

        $notes = '';
        $shape = '';
        foreach ($codes as $code) {
            $l = $languages[$code];
            $notes .= "LANGUAGE: {$l['name']} ({$code}), {$l['native']}, ".($l['rtl'] ? 'written from right to left' : 'written from left to right')."\n";
            $notes .= $l['quran'] !== ''
                ? "- The translation line of a Quran verse: the verse in the translation of {$l['quran']}, word for word as it is on Quran.com. If you cannot copy it exactly, keep the English line and write (English) after it.\n"
                : "- No translation line for a Quran verse: keep the Arabic verse and the reference in brackets, and leave the English line out.\n";
            $notes .= "- Word list (use it every time, in the same places as in the English story):\n";
            foreach (preg_split('/\n/', $l['words']) as $line) {
                if (trim($line) !== '') {
                    $notes .= '    '.trim($line)."\n";
                }
            }
            $notes .= "\n";

            $shape .= "LANG: {$code}\nTITLE: ({$l['name']} title)\nSHORT: (the short text)\nCHAPTER: (chapter title)\nSLIDE 1\nHEADING: ...\nBODY: ...\nALT: ...\nSLIDE 2\n...\n(all the chapters and slides, exactly as many as in the English)\nKEYWORDS: (words people would search for, in {$l['name']}, separated by commas)\nMETA TITLE: ...\nMETA DESCRIPTION: ...\n====\n";
        }
        $shape = preg_replace('/====\n$/', "END\n", $shape);

        return Rules::translation()
            ."\n\n==================================================\nTHE STORY TO TRANSLATE (in English)\n\n"
            ."It has {$chapters} chapter(s) and {$slides} slide(s). Every language must have exactly {$chapters} chapter(s) and {$slides} slide(s), in the same order.\n\n"
            .static::source($story)
            ."\n==================================================\nTHE LANGUAGES TO WRITE\n\n"
            .($notes !== '' ? $notes : "(choose at least one language)\n\n")
            ."Answer with plain text only, in exactly the shape below, one block for each language, in this order. No words before it or after it.\n"
            ."Put the whole answer inside ONE code block (three backticks before it and three after it), so that the line breaks are kept when it is copied.\n\n"
            .($shape !== '' ? $shape : "(no language chosen)\n");
    }
}
