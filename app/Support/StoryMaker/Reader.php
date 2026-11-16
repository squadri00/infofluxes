<?php

namespace App\Support\StoryMaker;

use App\Models\Story;
use App\Support\QuranRefs;

/**
 * Reads the answer of the AI for step 1 and checks it. It saves nothing.
 * The result has errors (the story cannot be saved), warnings (it can, but look at them), a list "please check" for the person,
 * and the story itself: title, short text, style, chapters with their slides, keywords and the meta lines.
 */
class Reader
{
    private const KEYS = 'TYPE|CATEGORY|TITLE|SHORT|STYLE|CHAPTER|HEADING|BODY|PICTURE|ALT|KEYWORDS|META TITLE|META DESCRIPTION|PLEASE CHECK|SOURCES|REVIEWER';

    /** Words in one slide: below the first the slide is too thin, above the second too heavy for a picture. */
    public const BODY_MIN = 12;

    public const BODY_MAX = 70;

    /** The most that is comfortable to read out loud and to read on a phone. */
    public const SENTENCE_AVERAGE_MAX = 13;

    public const SENTENCE_MAX = 22;

    /** @return array{errors: list<string>, warnings: list<string>, check: list<string>, story: array<string, mixed>} */
    public static function read(string $answer, ?int $ownStoryId = null, ?int $askedSlides = null): array
    {
        $errors = [];
        $warnings = [];
        $check = [];

        $f = static::parse($answer);
        $title = trim($f['TITLE'] ?? '');
        $short = trim($f['SHORT'] ?? '');
        $chapters = $f['chapters'];

        if (strtoupper(trim(strtok((string) ($f['TYPE'] ?? ''), "\n"))) !== 'STORY') {
            $errors[] = 'The first line must say "TYPE: story".';
        }
        if ($title === '') {
            $errors[] = 'TITLE is missing.';
        } elseif (mb_strlen($title) > 120) {
            $errors[] = 'TITLE is longer than 120 letters.';
        }
        if ($short === '') {
            $warnings[] = 'SHORT is missing: the one or two sentences that say what the story is about.';
        } elseif (mb_strlen($short) > 400) {
            $errors[] = 'SHORT is longer than 400 letters.';
        }
        if ($title !== '' && Story::withTrashed()->get()->contains(fn (Story $s) => mb_strtolower($s->mainTitle()) === mb_strtolower($title) && ! ($ownStoryId !== null && $s->id === $ownStoryId))) {
            $errors[] = 'A story with this title already exists.';
        }

        // The size of the story
        $slideCount = array_sum(array_map(fn ($c) => count($c['slides']), $chapters));
        if ($slideCount < 1) {
            $errors[] = 'The story has no slides.';
        } elseif ($askedSlides !== null && $slideCount !== $askedSlides) {
            $warnings[] = 'You asked for '.$askedSlides.' slides and the answer has '.$slideCount.'. That is fine if you like the story as it is.';
        }
        if (count($chapters) === 1 && $slideCount > 0) {
            $warnings[] = 'The story has only one chapter. A story needs at least an opening and a closing chapter.';
        }
        $last = end($chapters);
        if ($last && ! str_contains(mb_strtolower($last['title']), 'closing')) {
            $warnings[] = 'The last chapter is "'.mb_substr($last['title'], 0, 60).'". It should be "Closing and final remarks".';
            $check[] = 'The last chapter is not the closing chapter. Add or rename it: "Closing and final remarks".';
        }
        foreach ($chapters as $c) {
            if (trim($c['title']) === '') {
                $warnings[] = 'A chapter has no title.';
            }
            if ($c['slides'] === []) {
                $warnings[] = 'The chapter "'.mb_substr($c['title'], 0, 50).'" has no slides. It is left out when the story is saved.';
            }
        }

        // Each slide
        $n = 0;
        $allBody = '';
        $arabic = [];
        $words = 0;
        $sentences = 0;
        $longSentences = [];
        foreach ($chapters as $c) {
            foreach ($c['slides'] as $s) {
                $n++;
                $heading = trim($s['heading']);
                $body = trim($s['body']);
                if ($heading === '') {
                    $errors[] = 'Slide '.$n.' has no HEADING.';
                } elseif (mb_strlen($heading) > 90) {
                    $warnings[] = 'The heading of slide '.$n.' is long ('.mb_strlen($heading).' letters). A few plain words is best.';
                }
                if ($body === '') {
                    $errors[] = 'Slide '.$n.' has no BODY.';

                    continue;
                }
                $allBody .= "\n".$body;
                [$prose, $hasArabic] = static::prose($body);
                if ($hasArabic) {
                    $arabic[] = $n;
                }
                $w = static::words($prose);
                if ($w < self::BODY_MIN) {
                    $warnings[] = 'Slide '.$n.' is very short ('.$w.' words). About 25 to 45 words is best.';
                } elseif ($w > self::BODY_MAX) {
                    $warnings[] = 'Slide '.$n.' is long ('.$w.' words). About 25 to 45 words is best, so the picture and the words fit together.';
                }
                foreach (static::sentences($prose) as $sentence) {
                    $sw = static::words($sentence);
                    $words += $sw;
                    $sentences++;
                    if ($sw > self::SENTENCE_MAX) {
                        $longSentences[] = $sentence;
                    }
                }
                if (trim($s['picture']) === '') {
                    $warnings[] = 'Slide '.$n.' has no PICTURE line, so you will not know what picture to make for it.';
                }
                if (trim($s['alt']) === '') {
                    $warnings[] = 'Slide '.$n.' has no ALT line (the description of the picture).';
                }
            }
        }

        // How simple the telling is
        if ($sentences > 0) {
            $average = round($words / $sentences, 1);
            if ($average > self::SENTENCE_AVERAGE_MAX || $longSentences !== []) {
                $warnings[] = 'The telling is not simple enough: the sentences have '.$average.' words on average (the target is 8 to 10, the limit '.self::SENTENCE_AVERAGE_MAX.')'
                    .($longSentences !== [] ? ' and '.count($longSentences).' sentence(s) have more than '.self::SENTENCE_MAX.' words' : '').'. Ask the AI to use shorter sentences and simpler words.';
                foreach (array_slice($longSentences, 0, 3) as $sentence) {
                    $check[] = 'Make this sentence shorter: "'.mb_substr($sentence, 0, 110).(mb_strlen($sentence) > 110 ? '…' : '').'"';
                }
            }
        }

        // Quotations
        foreach ($arabic as $slideNumber) {
            $check[] = 'Slide '.$slideNumber.' has Arabic text (a verse or a saying). Compare it letter by letter with Quran.com or Sunnah.com.';
        }
        foreach (QuranRefs::find($allBody."\n".($short)) as $q) {
            if (! $q['valid']) {
                $warnings[] = 'Quran reference '.$q['ref'].' cannot be right: '.$q['problem'].'.';
                $check[] = 'Quran reference '.$q['ref'].' does not exist ('.$q['problem'].').';
            }
        }

        // Salutations
        $texts = $title."\n".$short."\n".$allBody;
        foreach (static::salutationProblems($texts) as $p) {
            $warnings[] = $p;
        }

        // Things the story must not contain
        if (preg_match('~https?://|www\.~i', $texts)) {
            $warnings[] = 'The text has a web address. A story does not have web addresses in its slides.';
        }
        if (strip_tags($texts) !== $texts) {
            $warnings[] = 'The text has "<" and ">" signs. They are shown as plain text, not as formatting.';
        }
        if (! empty($f['SOURCES'])) {
            $warnings[] = 'A SOURCES list was given. A story does not need one. If you want references, put them in the closing chapter.';
        }
        if (isset($f['REVIEWER']) && $f['REVIEWER'] !== '') {
            $warnings[] = 'REVIEWER was ignored.';
        }
        $picturesText = implode(' ', array_map(fn ($c) => implode(' ', array_column($c['slides'], 'picture')), $chapters));
        if (preg_match('/\b(face of|faces? of|showing the face)\b[^.]{0,60}\b(prophet|muhammad|messenger|companion|angel|allah)\b/i', $picturesText)) {
            $warnings[] = 'A PICTURE line seems to show the face of a prophet, a companion or an angel. Change it before you make the picture.';
            $check[] = 'A picture line may show a face of a prophet, a companion or an angel. It must not.';
        }

        $pleaseCheck = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/^\s*[-•]\s*/u', '', $l)), explode("\n", (string) ($f['PLEASE CHECK'] ?? '')))));
        $check = array_slice(array_values(array_unique(array_merge($pleaseCheck, $check))), 0, 60);

        $keywords = collect(explode(',', (string) ($f['KEYWORDS'] ?? '')))->map(fn ($w) => trim(mb_substr(trim($w), 0, 60)))->filter()->unique()->take(20)->values()->all();
        $metaTitle = static::oneLine($f['META TITLE'] ?? '');
        $metaDescription = static::oneLine($f['META DESCRIPTION'] ?? '');
        if (mb_strlen($metaTitle) > 70) {
            $warnings[] = 'META TITLE is '.mb_strlen($metaTitle).' letters. Google shows about 60.';
        }
        if (mb_strlen($metaDescription) > 170) {
            $warnings[] = 'META DESCRIPTION is '.mb_strlen($metaDescription).' letters. Google shows about 155 to 160.';
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'check' => $check,
            'story' => [
                'title' => $title, 'short' => $short, 'style' => trim($f['STYLE'] ?? ''), 'keywords' => $keywords,
                'meta_title' => $metaTitle !== '' ? $metaTitle : null, 'meta_description' => $metaDescription !== '' ? $metaDescription : null,
                'chapters' => array_values(array_filter($chapters, fn ($c) => $c['slides'] !== [])),
                'slide_count' => $slideCount,
            ],
        ];
    }

    /** The answer cut into its fields, chapters and slides. Marks like ** and a code block around it are ignored. */
    public static function parse(string $answer): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $answer));
        $f = ['chapters' => []];
        $ci = -1;          // the chapter that is open
        $si = -1;          // the slide that is open, inside that chapter
        $target = null;    // where a following line belongs
        $mark = '(?:\*\*|__)?';
        $blankSlide = ['heading' => '', 'body' => '', 'picture' => '', 'alt' => ''];

        $startSlide = function () use (&$f, &$ci, &$si, $blankSlide) {
            if ($ci < 0) {
                $f['chapters'][] = ['title' => '', 'slides' => []];
                $ci = 0;
            }
            $f['chapters'][$ci]['slides'][] = $blankSlide;
            $si = count($f['chapters'][$ci]['slides']) - 1;
        };

        foreach ($lines as $line) {
            if (preg_match('/^\s*```/', $line)) {
                continue;
            }
            if (preg_match('/^\s*'.$mark.'END'.$mark.'\s*$/i', $line)) {
                break;
            }
            if (preg_match('/^\s*'.$mark.'SLIDE(?:\s+\d+)?'.$mark.'\s*:?\s*'.$mark.'\s*$/i', $line)) {
                $startSlide();
                $target = null;

                continue;
            }
            if (preg_match('/^\s*'.$mark.'('.self::KEYS.')(?:\s+\d+)?'.$mark.'\s*:\s*'.$mark.'\s?(.*)$/iu', $line, $m)) {
                $key = strtoupper(preg_replace('/\s+/', ' ', $m[1]));
                $value = $m[2];

                if ($key === 'CHAPTER') {
                    $f['chapters'][] = ['title' => trim($value), 'slides' => []];
                    $ci = count($f['chapters']) - 1;
                    $si = -1;
                    $target = null;

                    continue;
                }
                if (in_array($key, ['HEADING', 'BODY', 'PICTURE', 'ALT'], true)) {
                    if ($si < 0 || ($key === 'HEADING' && $f['chapters'][$ci]['slides'][$si]['heading'] !== '')) {
                        $startSlide();   // a slide whose "SLIDE" line was left out
                    }
                    $field = strtolower($key);
                    $f['chapters'][$ci]['slides'][$si][$field] = $value;
                    $target = ['slide', $field];

                    continue;
                }
                $f[$key] = $value;
                $target = in_array($key, ['SHORT', 'PLEASE CHECK', 'SOURCES'], true) ? ['top', $key] : null;

                continue;
            }
            // A following line belongs to the field that is open.
            if ($target === null) {
                continue;
            }
            if ($target[0] === 'slide' && $si >= 0) {
                $field = $target[1];
                $f['chapters'][$ci]['slides'][$si][$field] .= ($field === 'body' ? "\n" : ' ').$line;
            } elseif ($target[0] === 'top') {
                $f[$target[1]] = ($f[$target[1]] ?? '')."\n".$line;
            }
        }

        foreach ($f as $k => $v) {
            if (is_string($v)) {
                $f[$k] = trim($v);
            }
        }
        foreach ($f['chapters'] as $c => $chapter) {
            $f['chapters'][$c]['title'] = trim($chapter['title']);
            foreach ($chapter['slides'] as $i => $slide) {
                foreach (['heading', 'body', 'picture', 'alt'] as $field) {
                    $f['chapters'][$c]['slides'][$i][$field] = trim(preg_replace("/\n{3,}/", "\n\n", (string) $slide[$field]));
                }
                $f['chapters'][$c]['slides'][$i]['heading'] = trim(preg_replace('/\s+/', ' ', $f['chapters'][$c]['slides'][$i]['heading']));
            }
        }

        return $f;
    }
    /** The words that are told (verses in Arabic and the reference lines in brackets are left out of the count). @return array{0: string, 1: bool} */
    private static function prose(string $body): array
    {
        $hasArabic = false;
        $keep = [];
        foreach (preg_split('/\n+/', $body) as $line) {
            if (preg_match('/\p{Arabic}/u', $line)) {
                $hasArabic = true;

                continue;
            }
            if (preg_match('/^\s*[(\[].*[)\]]\s*$/u', $line) && preg_match('/\d/', $line)) {
                continue;
            }
            $keep[] = $line;
        }

        return [trim(implode(' ', $keep)), $hasArabic];
    }

    /** @return list<string> */
    private static function sentences(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?؟।])\s+/u', $text))));
    }

    private static function words(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    }

    private static function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    }

    /** Names that need their salutation. One note for each kind, with the number of places. @return list<string> */
    private static function salutationProblems(string $text): array
    {
        $out = [];
        $count = fn (string $pattern) => preg_match_all($pattern, $text);

        if (($n = $count('/\bMuhammad\b(?!\s*ﷺ)/u')) > 0) {
            $out[] = 'Prophet Muhammad is named '.$n.' time(s) without ﷺ after the name.';
        }
        if (($n = $count('/\bAllah\b(?![\x{2019}\']s)(?!\s*\(SWT\))/u')) > 0) {
            $out[] = 'Allah is named '.$n.' time(s) without (SWT) after the name.';
        }
        $prophets = 'Adam|Idris|Nuh|Noah|Hud|Salih|Ibrahim|Abraham|Lut|Lot|Ismail|Ishmael|Ishaq|Isaac|Yaqub|Jacob|Yusuf|Joseph|Shuayb|Ayyub|Job|Musa|Moses|Harun|Aaron|Dhul-Kifl|Dawud|David|Sulayman|Solomon|Ilyas|Elijah|Alyasa|Elisha|Yunus|Jonah|Zakariya|Yahya|John|Isa|Jesus';
        if (($n = $count('/\b('.$prophets.')\b(?!\s*\(AS\))(?![\x{2019}\']s\s*\(AS\))/u')) > 0) {
            $out[] = 'The name of a prophet appears '.$n.' time(s) without (AS) after it. (Check the places: some may be other people with the same name.)';
        }

        return $out;
    }
}
