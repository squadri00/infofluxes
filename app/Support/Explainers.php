<?php

namespace App\Support;

use App\Models\Explainer;
use Illuminate\Support\Facades\Cache;

/**
 * Word notes in a text.
 * - render(): turns @[word] (or @[word|shown text]) in a text into a tappable word. Only a published note is shown. The first time a word
 *   appears on a page it is tappable; later times it is plain. A word with no published note is just its words, never a broken mark.
 * - mark(): puts @[word] in a text at the first place each given word appears (used by the AI import).
 * Neither touches links, headings, quotations, code or buttons.
 */
class Explainers
{
    private const CACHE_KEY = 'explainers_map_v1';

    /** The most notes an article gets from one AI answer. */
    public const MAX_PER_ARTICLE = 12;

    /** Where a mark may not be put or shown: inside these tags. */
    private const NO_RENDER = ['a', 'button', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'summary', 'code', 'pre', 'script', 'style'];

    private const NO_MARK = ['a', 'button', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'summary', 'code', 'pre', 'script', 'style', 'blockquote', 'cite'];

    private const VOID = ['br', 'hr', 'img', 'input', 'meta', 'link', 'wbr'];

    /** @@[ term | shown ]  (no brackets, bars or tags inside) */
    private const TOKEN = '/@\[([^\[\]|<>]{1,80})(?:\|([^\[\]<>]{1,80}))?\]/u';

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** plain word => the id of its published note. @return array<string, int> */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $map = [];
            foreach (Explainer::query()->published()->orderBy('id')->get() as $e) {
                foreach ($e->lookupWords() as $word) {
                    $map[$word] ??= $e->id;
                }
            }

            return $map;
        });
    }

    /**
     * @return array{html: string, used: list<Explainer>}  the text, and the notes that are tappable in it (in order of appearance)
     */
    public static function render(string $html): array
    {
        if (! str_contains($html, '@[')) {
            return ['html' => $html, 'used' => []];
        }
        $map = static::map();
        $found = [];
        $order = [];
        $out = static::walk($html, self::NO_RENDER, function (string $text) use ($map, &$found, &$order) {
            return preg_replace_callback(self::TOKEN, function (array $m) use ($map, &$found, &$order) {
                $word = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $shown = isset($m[2]) && $m[2] !== '' ? $m[2] : $m[1];
                $id = $map[Synonyms::plain($word)] ?? null;
                if ($id === null || isset($found[$id])) {
                    return $shown;   // no published note, or it was tappable earlier on this page: plain words
                }
                $found[$id] = true;
                $order[] = $id;

                return '<button type="button" class="exp" data-exp="'.$id.'" aria-expanded="false" aria-controls="exp-card-'.$id.'">'.$shown.'</button>';
            }, $text);
        });
        $used = $order === [] ? [] : Explainer::query()->published()->whereIn('id', $order)->get()->sortBy(fn ($e) => array_search($e->id, $order))->values()->all();

        return ['html' => $out, 'used' => $used];
    }

    /**
     * Puts @[word] at the first place each word is found (a whole word, any capital letters). A place where a mark is already
     * is skipped. Returns the text and which of the given entries were marked.
     *
     * @param  list<array{term: string, aliases?: list<string>}>  $targets
     * @return array{html: string, marked: list<string>, missing: list<string>}
     */
    public static function mark(string $html, array $targets): array
    {
        $marked = [];
        $missing = [];
        foreach ($targets as $target) {
            $words = array_filter(array_merge([$target['term']], (array) ($target['aliases'] ?? [])), fn ($w) => preg_match('/^[^\[\]|<>]{1,80}$/u', (string) $w));
            // The longest spelling first, so "Day of Judgment" wins over "Day".
            usort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            $done = false;
            foreach ($words as $word) {
                $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/iu';
                $html = static::walk($html, self::NO_MARK, function (string $text) use ($pattern, $target, &$done) {
                    if ($done || str_contains($text, '@[') && preg_match(self::TOKEN, $text)) {
                        return $text;
                    }
                    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (! preg_match($pattern, $decoded, $m, PREG_OFFSET_CAPTURE)) {
                        return $text;
                    }
                    $done = true;
                    [$hit, $offset] = $m[0];
                    $shown = $hit === $target['term'] ? '' : '|'.$hit;

                    return e(substr($decoded, 0, $offset)).'@['.e($target['term']).($shown === '' ? '' : '|'.e($hit)).']'.e(substr($decoded, $offset + strlen($hit)));
                });
                if ($done) {
                    break;
                }
            }
            $done ? $marked[] = $target['term'] : $missing[] = $target['term'];
        }

        return ['html' => $html, 'marked' => $marked, 'missing' => $missing];
    }

    /**
     * The EXPLAINERS lines of an AI answer: "- word | kind | note | web address | other spellings".
     *
     * @return array{notes: list<array{term: string, kind: string, text: string, source: ?string, aliases: list<string>}>, problems: list<string>}
     */
    public static function readAnswer(string $text): array
    {
        $notes = [];
        $problems = [];
        $seen = [];
        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            $line = trim(preg_replace('/^\s*[-•*]\s*/u', '', $line));
            if ($line === '' || preg_match('/^(none|n\/a|\(.*\))$/i', $line)) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $term = $parts[0];
            $rest = array_slice($parts, 1);
            $kind = 'word';
            if (isset($rest[0]) && isset(Explainer::KINDS[mb_strtolower($rest[0])])) {
                $kind = mb_strtolower(array_shift($rest));
            }
            $body = (string) array_shift($rest);
            $source = null;
            $aliases = [];
            foreach ($rest as $extra) {
                if (preg_match('~^https?://\S+$~i', $extra) && $source === null) {
                    $source = $extra;
                } elseif ($extra !== '') {
                    $aliases = array_merge($aliases, array_filter(array_map('trim', explode(',', $extra))));
                }
            }
            if ($term === '' || preg_match('/[\[\]|<>]/u', $term) || mb_strlen($term) > 80) {
                $problems[] = 'A word note was skipped because its word is empty, too long, or has a [ ] | < > sign: '.mb_substr($term, 0, 40);

                continue;
            }
            if ($body === '') {
                $problems[] = 'A word note was skipped because it has no note: '.$term;

                continue;
            }
            $key = Synonyms::plain($term);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $notes[] = ['term' => $term, 'kind' => $kind, 'text' => $body, 'source' => $source, 'aliases' => array_values(array_filter(array_unique($aliases), fn ($a) => ! preg_match('/[\[\]|<>]/u', $a) && mb_strlen($a) <= 80))];
        }

        return ['notes' => $notes, 'problems' => $problems];
    }

    /** The note that already answers to this word (any status, not in the Trash), or null. */
    public static function existing(array $note): ?Explainer
    {
        $words = Synonyms::cleanTerms(array_merge([$note['term']], $note['aliases'] ?? []));

        return Explainer::query()->get()->first(fn (Explainer $e) => array_intersect($e->lookupWords(), $words) !== []);
    }

    /** Marks the first place of every note's word in the article text. Words not found are left out. */
    public static function applyNotes(string $html, array $notes): string
    {
        if ($notes === []) {
            return $html;
        }

        return static::mark($html, array_map(fn ($n) => ['term' => $n['term'], 'aliases' => $n['aliases'] ?? []], $notes))['html'];
    }

    /**
     * Makes the notes that do not exist yet, as drafts (they are shown to readers only when a person has read and published them).
     *
     * @return array{created: int, reused: int}
     */
    public static function createDrafts(array $notes, ?\App\Models\User $user): array
    {
        $created = 0;
        $reused = 0;
        $locale = config('infofluxes.default_locale');
        foreach ($notes as $note) {
            if (static::existing($note)) {
                $reused++;

                continue;
            }
            Explainer::create([
                'term' => [$locale => $note['term']], 'body' => [$locale => $note['text']], 'kind' => $note['kind'],
                'aliases' => $note['aliases'] ?: null, 'source_line' => $note['source'], 'status' => 'draft', 'ai_assisted' => true, 'author_id' => $user?->id,
            ]);
            $created++;
        }

        return ['created' => $created, 'reused' => $reused];
    }

    /** Does this text contain a mark for this word already? */
    public static function hasMark(string $html, string $term): bool
    {
        return (bool) preg_match('/@\['.preg_quote($term, '/').'(\||\])/iu', $html);
    }

    /**
     * Goes through the text node by node (not inside the tags in $skip) and lets $change rewrite each piece of plain text.
     * The tags themselves are never changed.
     */
    private static function walk(string $html, array $skip, \Closure $change): string
    {
        $parts = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $stack = [];
        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (preg_match('~^<\s*(/?)\s*([a-z0-9]+)~i', $part, $t)) {
                    $name = strtolower($t[2]);
                    if ($t[1] === '/') {
                        $i = array_search($name, array_reverse($stack, true), true);
                        if ($i !== false) {
                            $stack = array_slice($stack, 0, $i);
                        }
                    } elseif (! in_array($name, self::VOID, true) && ! str_ends_with($part, '/>')) {
                        $stack[] = $name;
                    }
                }
                $out .= $part;

                continue;
            }
            $inside = array_intersect($stack, $skip) !== [];
            $out .= $inside ? $part : $change($part);
        }

        return $out;
    }
}
