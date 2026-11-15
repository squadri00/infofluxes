<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\ImportLog;
use App\Models\User;

/**
 * Reads the answer of an AI chat that was pasted into the AI import screen.
 *
 * The answer is plain labelled text (see resources/prompts). It is only READ here: nothing is saved until commit(),
 * and commit() saves only what has no problem. Everything saved is a draft (articles) or hidden (categories),
 * and an article is marked as written with AI help, so a person must put their name in "Reviewer" before it is published.
 * No HTML is accepted: the text is kept as written and shown safely.
 */
class AiImport
{
    public const MAX_CHARS = 200000;

    public const MAX_LONG = 60000;

    public const MAX_SOURCES = 60;

    public const MAX_ITEMS = 50;

    public const MAX_CATEGORY_LINES = 300;

    private const KEYS = ['TYPE', 'CATEGORY', 'ALSO IN', 'TITLE', 'SHORT', 'LONG', 'KEYWORDS', 'VIDEOS', 'SOURCES', 'SOURCE', 'PLEASE CHECK', 'REVIEWER'];

    /**
     * @return array<int, array{kind: string, title: string, errors: list<string>, warnings: list<string>, data: array<string, mixed>}>
     */
    public static function parse(string $text, bool $checkLinks = false, ?int $entryId = null): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", mb_substr($text, 0, self::MAX_CHARS));
        // A chat often wraps the answer in a code box. The fence lines are not part of the answer.
        $text = preg_replace('/^\s*```[a-z]*\s*$/mi', '', $text);

        $blocks = array_values(array_filter(array_map('trim', preg_split('/^\s*={4,}\s*$/m', $text)), fn ($b) => $b !== ''));
        $items = [];
        foreach ($blocks as $block) {
            if (count($items) >= self::MAX_ITEMS) {
                $items[] = ['kind' => 'article', 'title' => '(too many)', 'errors' => ['Only '.self::MAX_ITEMS.' items can be checked at one time. Paste the rest again after saving these.'], 'warnings' => [], 'data' => []];
                break;
            }
            $items[] = static::parseBlock($block, $checkLinks, $entryId);
        }

        // One index entry takes one article. The other articles of the same paste are held back.
        if ($entryId) {
            $seen = false;
            foreach ($items as $i => $item) {
                if (! in_array($item['kind'], ['article', 'fact'], true)) {
                    continue;
                }
                if ($seen) {
                    $items[$i]['errors'][] = 'An index entry can hold only one article. Paste this one again on its own, with its own index entry (or with no entry).';
                }
                $seen = true;
            }
        }

        return $items;
    }

    /** @return array{kind: string, title: string, errors: list<string>, warnings: list<string>, data: array<string, mixed>} */
    private static function parseBlock(string $block, bool $checkLinks = false, ?int $entryId = null): array
    {
        $fields = [];
        $current = null;
        $rawLines = [];
        foreach (explode("\n", $block) as $line) {
            if (preg_match('/^\s*END\s*$/i', $line)) {
                break;
            }
            $keys = implode('|', array_map(fn ($k) => str_replace(' ', '\s+', $k), self::KEYS));
            // An AI often writes **TITLE:** or "TYPE: article" (with quote marks). The marks are ignored; only the key counts.
            $marks = '(?:\*\*|__)?';
            if (preg_match('/^\s*[\x{201C}\x{201D}"\']?\s*'.$marks.'(TYPE)'.$marks.'\s*:\s*'.$marks.'\s?(.*)$/iu', $line, $m)
                || preg_match('/^\s*'.$marks.'('.$keys.')'.$marks.'\s*:\s*'.$marks.'\s?(.*)$/iu', $line, $m)) {
                $current = strtoupper(preg_replace('/\s+/', ' ', $m[1]));
                $fields[$current] = $m[2];
                continue;
            }
            if ($current !== null) {
                $fields[$current] .= "\n".$line;
            } else {
                $rawLines[] = $line;
            }
        }
        $fields = array_map('trim', $fields);
        // For a categories answer the lines of the list follow "TYPE: categories", so the kind is the first line only.
        $kindRaw = trim((string) strtok($fields['TYPE'] ?? '', "\n"));
        $kind = strtolower($kindRaw);
        $sameLine = '';
        if (preg_match('/^[\s"\x{201C}\x{201D}\'*_]*(article|fact|categories)\b(.*)$/iu', $kindRaw, $km)) {
            $kind = strtolower($km[1]);
            $sameLine = trim($km[2], " \t\"'*_-–—:.\u{201C}\u{201D}");
        }

        if ($kind === 'categories') {
            // The category lines come after "TYPE: categories" (they are in the TYPE value's following lines).
            $lines = array_filter(array_map('trim', explode("\n", $fields['TYPE'] ?? '')), fn ($l) => $l !== '');
            array_shift($lines);

            return static::checkCategories(array_values($lines));
        }
        if (! in_array($kind, ['article', 'fact'], true)) {
            return ['kind' => 'article', 'title' => mb_substr(trim($fields['TITLE'] ?? (implode(' ', $rawLines) ?: '(unknown)')), 0, 80), 'errors' => ['The first line must say "TYPE: article", "TYPE: fact" or "TYPE: categories".'], 'warnings' => [], 'data' => []];
        }

        if ($sameLine !== '' && trim($fields['TITLE'] ?? '') === '') {
            $fields['TITLE'] = $sameLine;
            $fields['__title_from_type'] = '1';
        }

        return static::checkArticle($kind, $fields, $checkLinks, $entryId);
    }

    /** @param array<string, string> $f */
    private static function checkArticle(string $kind, array $f, bool $checkLinks = false, ?int $entryId = null): array
    {
        $errors = [];
        $warnings = [];
        $title = trim($f['TITLE'] ?? '');
        $short = trim($f['SHORT'] ?? '');
        $long = trim($f['LONG'] ?? '');
        $source = trim($f['SOURCE'] ?? '');
        if (strtoupper($source) === 'NONE') {
            $source = '';
        }

        if ($title === '') {
            $errors[] = 'TITLE is missing.';
        } elseif (mb_strlen($title) > 200) {
            $errors[] = 'TITLE is longer than 200 letters.';
        }
        if (mb_strlen($short) > 600) {
            $errors[] = 'SHORT is longer than 600 letters.';
        }
        if ($long === '') {
            $errors[] = 'LONG (the text) is missing.';
        } elseif (mb_strlen($long) > self::MAX_LONG) {
            $errors[] = 'LONG is longer than '.number_format(self::MAX_LONG).' letters.';
        }
        if (mb_strlen($source) > 500) {
            $errors[] = 'SOURCE is longer than 500 letters. Keep it to one line.';
        }
        if (str_contains($source, "\n")) {
            $warnings[] = 'SOURCE had more than one line. Only the first line is kept.';
            $source = trim(strtok($source, "\n"));
        }
        if ($kind === 'fact') {
            if ($source === '') {
                $errors[] = 'A fact needs a SOURCE.';
            }
            if (mb_strlen($long) > 1500) {
                $warnings[] = 'A fact is meant to be short. The text is longer than 1,500 letters.';
            }
        } elseif ($source === '') {
            $warnings[] = 'There is no SOURCE line. Add the source before you publish.';
        }
        if (strip_tags($long.$short.$title) !== $long.$short.$title) {
            $warnings[] = 'The text has "<" and ">" signs. They are shown as plain text, not as formatting.';
        }
        if (! empty($f['__title_from_type'])) {
            $warnings[] = 'The title was on the TYPE line. It was read as the title: '.mb_substr($title, 0, 80);
        }
        $auto = [];
        $headings = Html::outline($long);
        if ($kind === 'article' && $headings === []) {
            // Copying from a chat window often drops the "## " marks. Short lines that stand over a paragraph are taken as headings.
            [$guessed, $count] = static::guessHeadings($long);
            if ($count >= 2) {
                $long = $guessed;
                $headings = Html::outline($long);
                $warnings[] = 'The "## " marks were missing (a chat window often drops them when you copy). '.$count.' short lines were read as headings. Check that they are right.';
                $auto[] = 'Headings were guessed from short lines. Check that every heading is a real heading and that no heading is missing.';
            } elseif (mb_strlen($long) > 2500) {
                $warnings[] = 'The text has no headings ("## "). It will be one long block, without drop-down parts.';
            }
        }

        // Sources: read the list, and ask every web address whether it opens.
        $sources = static::readSources((string) ($f['SOURCES'] ?? ''));
        if (count($sources) > self::MAX_SOURCES) {
            $warnings[] = 'More than '.self::MAX_SOURCES.' sources. Only the first '.self::MAX_SOURCES.' are kept.';
            $sources = array_slice($sources, 0, self::MAX_SOURCES);
        }
        if ($kind === 'article' && $sources === []) {
            $warnings[] = 'There is no SOURCES list. Do not publish an article without its sources.';
        }
        $checked = 0;
        foreach ($sources as $i => $src) {
            $label = mb_substr($src['ref'], 0, 70);
            if ($src['opened'] === false) {
                $warnings[] = 'The AI says it did not open this source: '.$label;
                $auto[] = 'Open this source yourself, the AI did not: '.$label;
            }
            if ($src['url'] === null) {
                $warnings[] = 'This source has no web address: '.$label;

                continue;
            }
            if ($checkLinks && $checked < LinkChecker::MAX_LINKS) {
                $checked++;
                $sources[$i]['link'] = LinkChecker::check($src['url']);
                if ($sources[$i]['link'] === 'broken') {
                    $warnings[] = 'This link did not open: '.mb_substr($src['url'], 0, 90);
                    $auto[] = 'A link did not open, find the right one: '.mb_substr($src['url'], 0, 90);
                } elseif ($sources[$i]['link'] === 'blocked') {
                    $warnings[] = 'This site does not allow automatic checks. Open the link yourself: '.mb_substr($src['url'], 0, 90);
                    $auto[] = 'Open this link yourself, the site does not allow automatic checks: '.mb_substr($src['url'], 0, 90);
                } elseif ($sources[$i]['link'] === 'skipped') {
                    $warnings[] = 'This address cannot be checked from here: '.mb_substr($src['url'], 0, 90);
                }
            }
        }

        // Quran references: only that the surah and verse exist.
        $quran = QuranRefs::find($long."\n".($f['SOURCES'] ?? ''));
        foreach ($quran as $q) {
            if (! $q['valid']) {
                $warnings[] = 'Quran reference '.$q['ref'].' cannot be right: '.$q['problem'].'.';
                $auto[] = 'Quran reference '.$q['ref'].' does not exist ('.$q['problem'].').';
            }
        }

        // Verse and hadith blocks: each must be complete.
        $blocks = Html::quoteBlocks($long);
        foreach ($blocks as $bl) {
            $what = $bl['type'] === 'hadith' ? 'hadith' : 'verse';
            $label = mb_substr($bl['ref'] !== '' ? $bl['ref'] : ($bl['text'] ?: $bl['arabic']), 0, 60);
            if ($bl['text'] === '') {
                $warnings[] = 'A '.$what.' block has no English words (TEXT): '.$label;
            }
            if ($bl['ref'] === '') {
                $warnings[] = 'A '.$what.' block has no reference (REF): '.$label;
                $auto[] = 'A '.$what.' has no reference. Find where it comes from: '.$label;

                continue;
            }
            if ($bl['type'] === 'quran') {
                if (QuranRefs::find($bl['ref']) === [] && ! preg_match('/\d+\s*:\s*\d+/', $bl['ref'])) {
                    $warnings[] = 'A verse block has no surah and verse number in its reference: '.$label;
                    $auto[] = 'A verse reference has no surah and verse number: '.$label;
                }
                if ($bl['arabic'] === '') {
                    $warnings[] = 'A verse block has no Arabic text: '.$label;
                    $auto[] = 'Add the Arabic text of the verse (copy it from Quran.com): '.$label;
                }
            } else {
                if (! preg_match('/\d/', $bl['ref'])) {
                    $warnings[] = 'A hadith reference has no number: '.$label;
                    $auto[] = 'A hadith reference has no number. Check it on Sunnah.com: '.$label;
                }
                if (! preg_match('/\b(sahih|saheeh|hasan|da[\x{2019}\']?if|daeef|weak|mawdu|fabricated|grade)\b/iu', $bl['ref'])) {
                    $warnings[] = 'A hadith reference has no grade: '.$label;
                    $auto[] = 'A hadith has no grade (sahih, hasan, daif). Find the grade and who gave it: '.$label;
                }
            }
        }

        // How easy the text is to read.
        $reading = ReadingLevel::measure($long);
        if ($reading['hard']) {
            $warnings[] = 'Hard to read: the sentences have '.$reading['average'].' words on average (the target is about 15, the limit '.config('infofluxes.reading.avg_max').')'
                .($reading['too_long'] ? ' and '.count($reading['too_long']).' sentence(s) are longer than '.config('infofluxes.reading.sentence_max').' words' : '').'. Make them shorter and simpler before you publish.';
            foreach (array_slice($reading['too_long'], 0, 3) as $sentence) {
                $auto[] = 'Make this sentence shorter and simpler: "'.mb_substr($sentence, 0, 110).(mb_strlen($sentence) > 110 ? '…' : '').'"';
            }
            if ($reading['average'] > (float) config('infofluxes.reading.avg_max')) {
                $auto[] = 'The whole text is hard to read for an ordinary reader. Simplify it.';
            }
        }

        $pleaseCheck = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/^\s*[-•]\s*/u', '', $l)), explode("\n", (string) ($f['PLEASE CHECK'] ?? '')))));
        $pleaseCheck = array_slice(array_values(array_unique(array_merge($pleaseCheck, $auto))), 0, 60);
        if (isset($f['REVIEWER']) && $f['REVIEWER'] !== '') {
            $warnings[] = 'REVIEWER was ignored. A person writes their own name there after reading the article.';
        }

        // The category
        $category = null;
        $categoryText = trim($f['CATEGORY'] ?? '');
        if ($categoryText === '') {
            $errors[] = 'CATEGORY is missing.';
        } else {
            [$category, $problem] = static::findCategory($categoryText);
            if ($problem) {
                $errors[] = 'CATEGORY: '.$problem;
            } elseif ($category && ! $category->isLeaf()) {
                $errors[] = 'CATEGORY: '.static::notLastLevel($category);
            }        }
        // ALSO IN is only a help. A name that does not fit is left out with a note; it never stops the article from being saved.
        $extra = [];
        foreach (array_filter(array_map('trim', preg_split('/[;\n]/', (string) ($f['ALSO IN'] ?? '')))) as $text) {
            [$other, $problem] = static::findCategory($text);
            $tries = [[$other, $problem, $text]];
            if ($problem && str_contains($text, ',')) {
                // "Tawhid, Worship in Islam": names written with commas instead of semicolons
                $tries = [];
                foreach (array_filter(array_map('trim', explode(',', $text))) as $piece) {
                    [$o, $p] = static::findCategory($piece);
                    $tries[] = [$o, $p, $piece];
                }
            }
            foreach ($tries as [$o, $p, $piece]) {
                if ($p) {
                    $warnings[] = 'ALSO IN: "'.mb_substr($piece, 0, 60).'" was left out. '.$p;
                } elseif ($o && ! $o->isLeaf()) {
                    $warnings[] = 'ALSO IN: "'.mb_substr($o->mainName(), 0, 60).'" was left out. '.static::notLastLevel($o);
                } elseif ($o && (! $category || $o->id !== $category->id)) {
                    $extra[$o->id] = $o;
                }
            }
        }

        // The index entry this article was written for (chosen on the form): it takes the entry's category and keeps its index ID.
        $entry = null;
        if ($entryId) {
            $entry = Article::query()->find($entryId);
            if (! $entry || ! IndexEntries::isBlank($entry)) {
                $errors[] = 'The index entry you chose is not blank any more, or it does not exist. Choose another entry, or choose no entry.';
                $entry = null;
            } else {
                if ($category && $category->id !== $entry->category_id) {
                    $warnings[] = 'The CATEGORY in the answer is not the category of the index entry. The category of the entry ('.IndexEntries::label($entry).') is used.';
                }
                $category = $entry->category;
                $errors = array_values(array_filter($errors, fn ($e) => ! str_starts_with($e, 'CATEGORY')));
            }
        }

        // A blank index entry about the same subject: offer to fill it instead of making a second article.
        $suggestions = [];
        if (! $entry && $kind === 'article' && $category && $title !== '') {
            $suggestions = IndexEntries::suggest($title, $category->id);
            foreach ($suggestions as $sg) {
                $warnings[] = 'This looks like the blank index entry '.$sg['code'].' "'.$sg['title'].'". Use that entry instead of making a second article about the same subject.';
            }
        }

        // The same title in the same category is not made twice.
        if ($category && $title !== '') {
            $same = Article::withTrashed()->where('category_id', $category->id)->get()->first(fn ($a) => mb_strtolower($a->mainTitle()) === mb_strtolower($title) && (! $entry || $a->id !== $entry->id));
            if ($same) {
                $errors[] = 'An article with this title is already in that category ('.$same->code.').';
            }
        }

        // Keywords and videos
        $words = collect(explode(',', (string) ($f['KEYWORDS'] ?? '')))->map(fn ($w) => trim(mb_substr(trim($w), 0, 60)))->filter()->unique()->values();
        if ($words->count() > 20) {
            $warnings[] = 'More than 20 KEYWORDS. Only the first 20 are kept.';
            $words = $words->take(20)->values();
        }
        $videos = [];
        foreach (array_filter(array_map('trim', preg_split('/[\s,]+/', (string) ($f['VIDEOS'] ?? '')))) as $url) {
            if (YouTube::id($url) === null) {
                $warnings[] = 'A video address was skipped because it is not a YouTube video: '.mb_substr($url, 0, 80);
            } else {
                $videos[] = $url;
            }
        }

        return [
            'kind' => $kind,
            'title' => $title !== '' ? $title : '(no title)',
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => [
                'title' => $title, 'short' => $short, 'long' => $long, 'source' => $source !== '' ? $source : null,
                'category_id' => $category?->id, 'category_label' => $category ? Category::choices()[$category->id] ?? $category->code : null,
                'extra_ids' => array_keys($extra), 'words' => $words->all(), 'videos' => $videos,
                'entry_id' => $entry?->id, 'entry_label' => $entry ? IndexEntries::label($entry).' · '.$entry->mainTitle() : null, 'index_suggestions' => $suggestions,
                'quotes' => $blocks, 'reading' => $reading, 'outline' => $headings, 'sources' => $sources, 'please_check' => $pleaseCheck, 'quran' => $quran,
            ],
        ];
    }

    /** The message for a category that has sub-categories: an article cannot be placed there. */
    private static function notLastLevel(Category $category): string
    {
        $examples = $category->leafExamples();

        return '"'.$category->mainName().'" has sub-categories, so an article cannot be placed in it. Use a last-level category'
            .($examples ? ', for example: '.implode('; ', $examples) : '').'.';
    }

    /**
     * Gives a text the "## " marks a chat window dropped: a short line without an end mark, with a blank line, the start or a finished sentence
     * before it and a longer line of text straight after it, is a heading. Verse and hadith blocks and list lines are never touched.
     *
     * @return array{0: string, 1: int} the new text and the number of headings made
     */
    public static function guessHeadings(string $text): array
    {
        $lines = explode("\n", str_replace("\r", '', $text));
        $out = [];
        $count = 0;
        $inBlock = false;
        $previousBlank = true;
        $previousEndsSentence = false;
        foreach ($lines as $i => $line) {
            $t = trim($line);
            if (preg_match('/^\[(QURAN|HADITH)\]$/i', $t)) {
                $inBlock = true;
            }
            $next = trim((string) ($lines[$i + 1] ?? ''));
            $words = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
            $looksLikeHeading = ! $inBlock && ($previousBlank || $previousEndsSentence) && $t !== '' && mb_strlen($t) <= 90 && count($words) <= 12
                && ! preg_match('/[.;:,!…]["”’\')\]]*$/u', $t)                 // a sentence ends with a mark; a heading does not
                && ! preg_match('/^(#|[-•*]\s|\[|[A-Z ]{3,}:)/u', $t)           // not already marked, not a list line, not a field
                && $next !== '' && ! preg_match('/^(#|\[)/', $next) && mb_strlen($next) > mb_strlen($t);
            $out[] = $looksLikeHeading ? '## '.$t : $line;
            $count += $looksLikeHeading ? 1 : 0;
            if (preg_match('/^\[\/(QURAN|HADITH)\]$/i', $t)) {
                $inBlock = false;
            }
            $previousBlank = $t === '';
            $previousEndsSentence = $t !== '' && ! $looksLikeHeading && (bool) preg_match('/[.!?]["\x{201D}\x{2019}\')\]]*$/u', $t);
        }

        return [implode("\n", $out), $count];
    }

    /**
     * Lines of the SOURCES field: "- PRIMARY | the source | web address | opened: yes". Missing parts are allowed.
     *
     * @return list<array{type: string, ref: string, url: ?string, opened: ?bool, link: ?string}>
     */
    public static function readSources(string $text): array
    {
        $out = [];
        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            $line = trim(preg_replace('/^\s*[-•*]\s*/u', '', $line));
            if ($line === '' || preg_match('/^(none|n\/a)$/i', $line)) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $type = 'other';
            if (preg_match('/^(primary|secondary)\b/i', $parts[0] ?? '', $tm)) {
                $type = strtolower($tm[1]);
                array_shift($parts);
            }
            $url = null;
            $opened = null;
            $refs = [];
            foreach ($parts as $p) {
                if (preg_match('/^opened\s*:\s*(yes|no)/i', $p, $om)) {
                    $opened = strtolower($om[1]) === 'yes';
                } elseif (preg_match('#https?://[^\s|)\]>]+#i', $p, $um)) {
                    $url ??= rtrim($um[0], '.,;');
                    $rest = trim(str_replace($um[0], '', $p), " ()[]<>");
                    if ($rest !== '') {
                        $refs[] = $rest;
                    }
                } elseif ($p !== '') {
                    $refs[] = $p;
                }
            }
            $ref = mb_substr(implode(' | ', $refs), 0, 400);
            if ($ref === '' && $url === null) {
                continue;
            }
            $out[] = ['type' => $type, 'ref' => $ref !== '' ? $ref : (string) $url, 'url' => $url, 'opened' => $opened, 'link' => null];
        }

        return $out;
    }

    /**
     * A category written as a code (C-12), a path (Shows > Popular Shows) or a single unique name.
     *
     * @return array{0: ?Category, 1: ?string} the category, or a problem in plain words
     */
    public static function findCategory(string $text): array
    {
        $text = trim($text);
        if (preg_match('/^C-(\d+)$/i', $text, $m)) {
            $c = Category::find((int) $m[1]);

            return $c ? [$c, null] : [null, "no category has the code $text."];
        }
        // "Shows > Popular Shows (C-2)" also works: the code in brackets wins.
        if (preg_match('/\(C-(\d+)\)\s*$/i', $text, $m) && ($c = Category::find((int) $m[1]))) {
            return [$c, null];
        }

        $all = Category::query()->orderBy('tree_path')->get()->keyBy('id');
        $wanted = array_map(fn ($p) => mb_strtolower(trim($p)), explode('>', $text));
        $matches = [];
        foreach ($all as $category) {
            $path = array_map('mb_strtolower', array_map('trim', explode(' > ', Category::pathFor($category, $all))));
            // A written path may start anywhere in the tree but must end at this category.
            if (array_slice($path, -count($wanted)) === $wanted) {
                $matches[] = $category;
            }
        }
        if (count($matches) === 1) {
            return [$matches[0], null];
        }
        if (count($matches) > 1) {
            return [null, 'the name "'.$text.'" matches more than one category. Write the code instead, for example '.$matches[0]->code.' or '.$matches[1]->code.'.'];
        }

        return [null, 'no category called "'.$text.'". Use the exact name or the code (C-12).'];
    }

    /** @param list<string> $lines */
    private static function checkCategories(array $lines): array
    {
        $errors = [];
        $warnings = [];
        $paths = [];
        if (count($lines) > self::MAX_CATEGORY_LINES) {
            $errors[] = 'More than '.self::MAX_CATEGORY_LINES.' lines. Paste fewer lines at one time.';
            $lines = array_slice($lines, 0, self::MAX_CATEGORY_LINES);
        }
        $new = 0;
        $existing = 0;
        foreach ($lines as $line) {
            [$pathText, $description] = array_pad(explode('|', $line, 2), 2, '');
            $names = array_values(array_filter(array_map('trim', explode('>', $pathText)), fn ($n) => $n !== ''));
            if ($names === []) {
                continue;
            }
            if (count($names) > 6) {
                $errors[] = 'Too deep (more than 6 levels): '.mb_substr($pathText, 0, 80);

                continue;
            }
            $tooLong = array_filter($names, fn ($n) => mb_strlen($n) > 120);
            if ($tooLong) {
                $errors[] = 'A name is longer than 120 letters: '.mb_substr(reset($tooLong), 0, 60).'...';

                continue;
            }
            $paths[] = ['names' => $names, 'description' => trim($description)];

            // Does the whole path exist already?
            $parent = null;
            $exists = true;
            foreach ($names as $name) {
                $found = Category::query()->where('parent_id', $parent?->id)->get()->first(fn ($c) => mb_strtolower($c->mainName()) === mb_strtolower($name));
                if (! $found) {
                    $exists = false;
                    if ($parent && $parent->holdsArticles()) {
                        $errors[] = '"'.$parent->mainName().'" already holds articles, so it cannot get sub-categories yet. Move its articles into its Overview category first: '.mb_substr($pathText, 0, 80);
                    }
                    break;
                }
                $parent = $found;
            }
            $exists ? $existing++ : $new++;
        }
        if ($paths === []) {
            $errors[] = 'There are no category lines. Write one line for each category, for example: Shows > Popular Shows';
        }
        if ($existing > 0) {
            $warnings[] = "$existing line(s) are categories that already exist. They are left as they are.";
        }

        return [
            'kind' => 'categories',
            'title' => count($paths).' line(s): '.$new.' new, '.$existing.' already there',
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => ['paths' => $paths, 'new' => $new, 'existing' => $existing],
        ];
    }

    /**
     * Save everything that has no problem. Articles become drafts marked as written with AI help; new categories are hidden.
     *
     * @param  array<int, array{kind: string, title: string, errors: list<string>, warnings: list<string>, data: array<string, mixed>}>  $items
     * @return array{articles: list<int>, categories: list<int>, skipped: int}
     */
    public static function commit(array $items, string $rawText, ?User $user): array
    {
        $made = ['articles' => [], 'categories' => [], 'skipped' => 0];
        $locale = config('infofluxes.default_locale');

        foreach ($items as $item) {
            if ($item['errors'] !== []) {
                $made['skipped']++;

                continue;
            }
            if ($item['kind'] === 'categories') {
                foreach ($item['data']['paths'] as $line) {
                    $parent = null;
                    foreach ($line['names'] as $i => $name) {
                        $found = Category::query()->where('parent_id', $parent?->id)->get()->first(fn ($c) => mb_strtolower($c->mainName()) === mb_strtolower($name));
                        if (! $found) {
                            $last = (int) Category::query()->where('parent_id', $parent?->id)->max('sort_order');
                            $isLast = $i === count($line['names']) - 1;
                            $found = Category::create([
                                'name' => [$locale => $name],
                                'slug' => Category::uniqueSlug($name),
                                'parent_id' => $parent?->id,
                                'sort_order' => $last + 10,
                                'is_visible' => false,   // made hidden: the owner shows it after looking
                                'description' => $isLast && $line['description'] !== '' ? [$locale => $line['description']] : null,
                            ]);
                            $made['categories'][] = $found->id;
                        }
                        $parent = $found;
                    }
                }

                continue;
            }

            $d = $item['data'];
            if (! empty($d['entry_id']) && ($entry = Article::find($d['entry_id'])) && IndexEntries::isBlank($entry)) {
                IndexEntries::fill($entry, $d, $user);
                $made['articles'][] = $entry->id;

                continue;
            }
            $article = Article::create([
                'title' => [$locale => $d['title']],
                'slug' => Article::uniqueSlug($d['title']),
                'category_id' => $d['category_id'],
                'short_detail' => [$locale => $d['short']],
                'long_detail' => [$locale => Html::fromMarked($d['long'])],
                'search_words' => $d['words'] ?: null,
                'source_line' => $d['source'],
                'author_id' => $user?->id,
                'status' => 'written',   // text is in, a person has not reviewed it yet
                'ai_assisted' => true,
                'custom_data' => array_filter([
                    'accordion' => count(array_filter($d['outline'], fn ($h) => $h['level'] === 2)) >= 2 ? true : null,
                    'sources' => $d['sources'] ?: null,
                    'please_check' => $d['please_check'] ?: null,
                ]) ?: null,
            ]);
            if ($d['extra_ids']) {
                $article->extraCategories()->sync($d['extra_ids']);
            }
            foreach ($d['videos'] as $i => $url) {
                $article->videos()->create(['url' => $url, 'sort_order' => $i + 1]);
            }
            $made['articles'][] = $article->id;
        }

        ImportLog::create([
            'user_id' => $user?->id,
            'kind' => collect($items)->pluck('kind')->unique()->count() === 1 ? $items[0]['kind'] : 'mixed',
            'raw_text' => mb_substr($rawText, 0, self::MAX_CHARS),
            'result' => $made,
        ]);

        return $made;
    }
}
