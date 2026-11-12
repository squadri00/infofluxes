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

    public const MAX_ITEMS = 50;

    public const MAX_CATEGORY_LINES = 300;

    private const KEYS = ['TYPE', 'CATEGORY', 'ALSO IN', 'TITLE', 'SHORT', 'LONG', 'KEYWORDS', 'VIDEOS', 'SOURCE', 'REVIEWER'];

    /**
     * @return array<int, array{kind: string, title: string, errors: list<string>, warnings: list<string>, data: array<string, mixed>}>
     */
    public static function parse(string $text): array
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
            $items[] = static::parseBlock($block);
        }

        return $items;
    }

    /** @return array{kind: string, title: string, errors: list<string>, warnings: list<string>, data: array<string, mixed>} */
    private static function parseBlock(string $block): array
    {
        $fields = [];
        $current = null;
        $rawLines = [];
        foreach (explode("\n", $block) as $line) {
            if (preg_match('/^\s*END\s*$/i', $line)) {
                break;
            }
            if (preg_match('/^\s*('.implode('|', array_map(fn ($k) => str_replace(' ', '\s+', $k), self::KEYS)).')\s*:\s?(.*)$/i', $line, $m)) {
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
        $kind = strtolower(trim((string) strtok($fields['TYPE'] ?? '', "\n")));

        if ($kind === 'categories') {
            // The category lines come after "TYPE: categories" (they are in the TYPE value's following lines).
            $lines = array_filter(array_map('trim', explode("\n", $fields['TYPE'] ?? '')), fn ($l) => $l !== '');
            array_shift($lines);

            return static::checkCategories(array_values($lines));
        }
        if (! in_array($kind, ['article', 'fact'], true)) {
            return ['kind' => 'article', 'title' => mb_substr(trim($fields['TITLE'] ?? (implode(' ', $rawLines) ?: '(unknown)')), 0, 80), 'errors' => ['The first line must say "TYPE: article", "TYPE: fact" or "TYPE: categories".'], 'warnings' => [], 'data' => []];
        }

        return static::checkArticle($kind, $fields);
    }

    /** @param array<string, string> $f */
    private static function checkArticle(string $kind, array $f): array
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
        } elseif (mb_strlen($long) > 30000) {
            $errors[] = 'LONG is longer than 30,000 letters.';
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
            }
        }
        $extra = [];
        foreach (array_filter(array_map('trim', preg_split('/;/', (string) ($f['ALSO IN'] ?? '')))) as $text) {
            [$other, $problem] = static::findCategory($text);
            if ($problem) {
                $errors[] = 'ALSO IN: '.$problem;
            } elseif ($other && (! $category || $other->id !== $category->id)) {
                $extra[$other->id] = $other;
            }
        }

        // The same title in the same category is not made twice.
        if ($category && $title !== '') {
            $same = Article::withTrashed()->where('category_id', $category->id)->get()->first(fn ($a) => mb_strtolower($a->mainTitle()) === mb_strtolower($title));
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
            ],
        ];
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
            $article = Article::create([
                'title' => [$locale => $d['title']],
                'slug' => Article::uniqueSlug($d['title']),
                'category_id' => $d['category_id'],
                'short_detail' => [$locale => $d['short']],
                'long_detail' => [$locale => Html::fromPlain($d['long'])],
                'search_words' => $d['words'] ?: null,
                'source_line' => $d['source'],
                'author_id' => $user?->id,
                'status' => 'draft',
                'ai_assisted' => true,
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
