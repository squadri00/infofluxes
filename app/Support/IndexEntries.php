<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;

/**
 * The planned subjects of the index (AL-2.1.001 "Allah (SWT) alone creates") are kept as draft articles with no text.
 * An article written with AI help should fill such a blank entry, not make a second article about the same subject.
 */
class IndexEntries
{
    /** Words that say nothing about the subject: they are in almost every title of a category. */
    private const STOP = ['allah', 'swt', 'the', 'of', 'is', 'are', 'was', 'a', 'an', 'and', 'or', 'to', 'for', 'in', 'on', 'at', 'with', 'by', 'from', 'as', 'it', 'its', 'his', 'her',
        'alone', 'all', 'what', 'does', 'do', 'how', 'why', 'who', 'when', 'where', 'which', 'that', 'this', 'about', 'mean', 'means'];

    /** A blank index entry: a planned subject with no text yet (and not archived or deleted). */
    public static function isBlank(Article $a): bool
    {
        return $a->is_index_line && $a->status !== 'archived' && ! $a->trashed()
            && trim(strip_tags((string) $a->getTranslation('long_detail', config('infofluxes.default_locale'), false))) === '';
    }

    /** The ID shown for an entry: its index ID (AL-2.1.001) when it has one, else its code (A-723). */
    public static function label(Article $a): string
    {
        return CategoryIndex::codeOf($a);
    }

    /**
     * Blank entries that match what was typed: an index ID (AL-2.1), a code (A-723 or 723) or words of the title.
     *
     * @return \Illuminate\Support\Collection<int, Article>
     */
    public static function search(string $q, ?int $categoryId = null, int $limit = 15)
    {
        $q = trim($q);
        $locale = config('infofluxes.default_locale');
        // A plain number or A-number is a code: if it names a blank entry, that entry is the answer.
        if (preg_match('/^(?:A-)?(\d+)$/i', $q, $num) && ($byCode = Article::query()->whereKey((int) $num[1])->get()->filter(fn ($a) => static::isBlank($a)))->isNotEmpty()) {
            return $byCode->values();
        }
        $query = Article::query()->where('is_index_line', true)->where('status', '!=', 'archived')
            ->where(fn ($b) => $b->whereNull('long_detail')->orWhere('long_detail', '')->orWhere("long_detail->{$locale}", '')->orWhereNull("long_detail->{$locale}"));
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q, $locale) {
                $w->where('working_name', 'like', '%'.$q.'%')->orWhere("title->{$locale}", 'like', '%'.$q.'%');
                if (preg_match('/^(?:A-)?(\d+)$/i', $q, $m)) {
                    $w->orWhere('id', (int) $m[1]);
                }
            });
        }

        // The JSON test above is wide on purpose; the exact test is isBlank().
        $needle = mb_strtolower($q);
        $rank = function (Article $a) use ($needle): int {
            $name = mb_strtolower((string) $a->working_name);
            $core = preg_replace('/^[a-z]{2}-/', '', $name);

            return $needle === '' ? 3 : ($name === $needle || $core === $needle ? 0 : (str_starts_with($core, $needle) ? 1 : 2));
        };

        // Entries whose ID is exactly what was typed come first, then those that start with it, then the rest.
        return $query->with('category')->orderBy('working_name')->orderBy('id')->limit($limit * 20)->get()->filter(fn ($a) => static::isBlank($a))
            ->sortBy(fn ($a) => $rank($a))->take($limit)->values();
    }

    /**
     * Blank entries of a category (and the branch below it) whose subject looks like this title.
     *
     * @return list<array{id: int, code: string, title: string, score: float}>
     */
    public static function suggest(string $title, ?int $categoryId, int $limit = 3): array
    {
        $want = static::tokens($title);
        if ($want === [] || ! $categoryId || ! ($category = Category::find($categoryId))) {
            return [];
        }
        $ids = $category->descendantIds();
        $entries = Article::query()->where('is_index_line', true)->where('status', '!=', 'archived')->whereIn('category_id', $ids)->limit(1500)->get();
        $found = [];
        foreach ($entries as $entry) {
            if (! static::isBlank($entry)) {
                continue;
            }
            $have = static::tokens($entry->mainTitle());
            if ($have === []) {
                continue;
            }
            $shared = array_intersect($have, $want);
            $score = count($shared) / count($have);
            if ($shared !== [] && $score >= 0.5) {
                $found[] = ['id' => $entry->id, 'code' => static::label($entry), 'title' => $entry->mainTitle(), 'score' => round($score, 2)];
            }
        }
        usort($found, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($found, 0, $limit);
    }

    /** The meaningful stems of a title: no stop words, simple endings removed ("creates" and "Creator" both become "creat"). @return list<string> */
    public static function tokens(string $title): array
    {
        $out = [];
        foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($title), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (mb_strlen($word) < 3 || in_array($word, self::STOP, true)) {
                continue;
            }
            $out[] = preg_replace('/(ations|ation|ings|ing|ors|or|ers|er|ions|ion|ed|es|e|s)$/u', '', $word) ?: $word;
        }

        return array_values(array_unique($out));
    }

    /**
     * Puts a checked AI answer into a blank entry. The entry keeps its index ID, its category and its place; the planned title is kept in custom_data.
     *
     * @param  array<string, mixed>  $d  the data of a checked item (see AiImport)
     */
    public static function fill(Article $entry, array $d, ?User $user): Article
    {
        $locale = config('infofluxes.default_locale');
        $custom = (array) $entry->custom_data;
        $custom['index_title'] ??= $entry->mainTitle();
        foreach (['accordion' => count(array_filter($d['outline'] ?? [], fn ($h) => $h['level'] === 2)) >= 2 ? true : null, 'sources' => ($d['sources'] ?? []) ?: null, 'please_check' => ($d['please_check'] ?? []) ?: null] as $k => $v) {
            if ($v !== null) {
                $custom[$k] = $v;
            }
        }

        $entry->setTranslation('title', $locale, $d['title']);
        $entry->setTranslation('short_detail', $locale, $d['short']);
        $entry->setTranslation('long_detail', $locale, Html::fromMarked($d['long']));
        $entry->slug = Article::uniqueSlug($d['title']);
        $entry->search_words = ($d['words'] ?? []) ?: null;
        $entry->source_line = $d['source'] ?? null;
        $entry->ai_assisted = true;
        $entry->author_id ??= $user?->id;
        $entry->custom_data = $custom;
        $entry->save();

        if (! empty($d['extra_ids'])) {
            $entry->extraCategories()->syncWithoutDetaching($d['extra_ids']);
        }
        foreach (($d['videos'] ?? []) as $i => $url) {
            $entry->videos()->create(['url' => $url, 'sort_order' => $i + 1]);
        }

        return $entry;
    }
}
