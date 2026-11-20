<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * The Index view of a category page: the whole branch below a category as a quick list of links, like the old index pages.
 * Headings (the categories) open and close, and the subjects (articles) are the lines. A subject that is not published yet but
 * is an index line shows as "details coming". Everything that is hidden stays hidden.
 *
 * An article that sits in more than one category is listed under each of them. The copy under an "also in" category carries a
 * small note with its main category, and the totals count every article once.
 *
 * A big branch is not drawn in one piece: after $budget lines, the remaining categories are shown as one link line each
 * that opens their own Index. So every page stays light.
 */
class CategoryIndex
{
    /** The most lines (categories and subjects) drawn on one page. */
    public static int $budget = 1500;

    private const COLUMNS = ['id', 'slug', 'title', 'status', 'publish_date', 'category_id', 'working_name', 'is_index_line', 'sort_order', 'custom_data'];

    /**
     * @return array{root: array<string, mixed>, subjects: int}
     */
    public static function build(Category $root, bool $preview): array
    {
        $cats = Category::query()
            ->where('tree_path', 'like', $root->tree_path.'/%')
            ->when(! $preview, fn ($q) => $q->where('is_visible', true))
            ->orderBy('tree_path')
            ->get();
        $kids = $cats->groupBy('parent_id');

        // Walk down from the root, so a hidden category takes everything under it out too.
        $tree = static::node($root, $kids);
        $ids = static::ids($tree);

        // The articles whose main category is in this branch, and those that are also in a category of it.
        $main = static::visible(Article::query()->select(self::COLUMNS)->whereIn('category_id', $ids))->get();
        $extraRows = DB::table('article_category')->whereIn('category_id', $ids)->get(['article_id', 'category_id']);
        $missing = $extraRows->pluck('article_id')->unique()->diff($main->pluck('id'))->all();
        $others = $missing ? static::visible(Article::query()->select(self::COLUMNS)->whereIn('id', $missing))->get() : collect();
        $all = $main->concat($others)->keyBy('id');

        // The name of the main category, for the note on a copy (only when a visitor may see that category).
        $mainIds = $all->pluck('category_id')->filter()->unique()->all();
        $mainNames = Category::query()->whereIn('id', $mainIds)->get()->keyBy('id');
        $open = $preview ? null : array_flip(array_keys(Category::publicChoices()));

        $lines = [];   // category id => list of [article, also]
        foreach ($main as $a) {
            $lines[$a->category_id][] = [$a, null];
        }
        foreach ($extraRows as $row) {
            $a = $all->get($row->article_id);
            if (! $a || (int) $a->category_id === (int) $row->category_id) {
                continue;
            }
            $home = $mainNames->get($a->category_id);
            $also = $home && ($open === null || isset($open[$home->id])) ? $home->mainName() : null;
            $lines[$row->category_id][] = [$a, $also ?? ''];
        }

        $tree = static::fill($tree, $lines, $preview);
        $subjects = count($tree['ids']);
        $remaining = static::$budget;
        $tree = static::fit($tree, $remaining, true);

        return ['root' => $tree, 'subjects' => $subjects];
    }

    /** What a visitor may see: published articles whose date has come, and index lines (planned subjects) that are not archived. */
    private static function visible($query)
    {
        return $query->where(fn ($q) => $q
            ->where(fn ($p) => $p->where('status', 'published')->where(fn ($d) => $d->whereNull('publish_date')->orWhere('publish_date', '<=', now())))
            ->orWhere(fn ($i) => $i->where('is_index_line', true)->where('status', '!=', 'archived')));
    }

    /** The code shown beside a heading or a subject: the old index ID (QR-1.1.001) when it has one, else the new code (C-12, A-42). */
    public static function codeOf(object $item): string
    {
        $name = (string) ($item->working_name ?? '');

        return preg_match('/^[A-Z]{2}-[0-9IVX]+(\.[0-9]+)*$/', $name) ? $name : $item->code;
    }

    /** Is this article listed anywhere in the branch (a heading and everything below it)? */
    public static function contains(array $node, int $articleId): bool
    {
        foreach ($node['articles'] as $line) {
            if ((int) $line['article']->id === $articleId) {
                return true;
            }
        }
        foreach ($node['children'] as $child) {
            if (static::contains($child, $articleId)) {
                return true;
            }
        }

        return false;
    }

    /** Is this category the branch itself, or anywhere below it? */
    public static function containsCategory(array $node, int $categoryId): bool
    {
        if ((int) $node['category']->id === $categoryId) {
            return true;
        }
        foreach ($node['children'] as $child) {
            if (static::containsCategory($child, $categoryId)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private static function node(Category $category, $kids): array
    {
        return [
            'category' => $category,
            'children' => ($kids->get($category->id) ?? collect())->map(fn ($c) => static::node($c, $kids))->all(),
            'articles' => [],
            'ids' => [],
            'size' => 0,
            'subjects' => 0,
            'truncated' => false,
        ];
    }

    /** @return list<int> */
    private static function ids(array $node): array
    {
        $ids = [$node['category']->id];
        foreach ($node['children'] as $child) {
            $ids = array_merge($ids, static::ids($child));
        }

        return $ids;
    }

    /**
     * Put the subjects into their category and work out how big each branch is.
     * 'ids' are the different articles in a branch, so an article that is in two places counts once.
     */
    private static function fill(array $node, array $lines, bool $preview): array
    {
        if ($node['virtual'] ?? false) {
            return $node;   // a Subtopic group is built ready, below its category
        }
        $mine = $lines[$node['category']->id] ?? [];
        usort($mine, fn ($x, $y) => [$x[0]->sort_order, $x[0]->id] <=> [$y[0]->sort_order, $y[0]->id]);

        $out = [];
        $ids = [];
        foreach ($mine as [$a, $also]) {
            $live = $a->status === 'published' && (! $a->publish_date || $a->publish_date->lte(now()));
            $out[] = [
                'article' => $a, 'code' => static::codeOf($a), 'title' => $a->title, 'link' => $live || $preview,
                'also' => $also !== null && $also !== '' ? $also : null, 'copy' => $also !== null, 'category_id' => $node['category']->id,
            ];
            $ids[$a->id] = true;
        }
        // A last-level category: its subjects are grouped under their Subtopic (for example "Prayer (Salah) > How Prayer Is Done").
        if (! $node['children']) {
            [$out, $node['children']] = static::group($out, 0);
        }
        $node['articles'] = $out;
        $node['children'] = array_map(fn ($c) => static::fill($c, $lines, $preview), $node['children']);
        foreach ($node['children'] as $child) {
            $ids += array_flip($child['ids']);
        }
        $node['ids'] = array_keys($ids);
        $node['subjects'] = count($node['ids']);
        $node['size'] = 1 + count($out) + array_sum(array_column($node['children'], 'size'));

        return $node;
    }

    /**
     * Put subject lines under headings made from their Subtopic ("Group > Subject": a heading, and a heading inside it).
     * The headings only exist in this view; lines without a Subtopic stay where they are.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private static function group(array $lines, int $depth, string $dom = ''): array
    {
        $rest = [];
        $groups = [];
        foreach ($lines as $line) {
            $parts = array_values(array_filter(array_map('trim', explode('>', (string) ($line['article']->custom_data['subtopic'] ?? '')))));
            if (! isset($parts[$depth])) {
                $rest[] = $line;

                continue;
            }
            $groups[$parts[$depth]][] = $line;
        }
        $nodes = [];
        foreach ($groups as $name => $inside) {
            $id = $dom.'g'.substr(md5($name), 0, 6);
            [$own, $kids] = static::group($inside, $depth + 1, $id);
            $cat = new Category;
            $cat->name = (string) $name;
            $subjects = [];
            foreach ($inside as $l) {
                $subjects[$l['article']->id] = true;
            }
            $nodes[] = [
                'category' => $cat, 'children' => $kids, 'articles' => $own, 'ids' => array_keys($subjects), 'subjects' => count($subjects),
                'size' => 1 + count($own) + array_sum(array_column($kids, 'size')), 'truncated' => false, 'virtual' => true, 'dom' => $id,
            ];
        }

        return [$rest, $nodes];
    }

    /** Draw branches in order while the budget lasts; a branch that no longer fits becomes one link line. */
    private static function fit(array $node, int &$remaining, bool $isRoot = false): array
    {
        if (! $isRoot && ! ($node['virtual'] ?? false) && $node['size'] > $remaining) {
            $node['truncated'] = true;
            $node['children'] = [];
            $node['articles'] = [];
            $remaining = max(0, $remaining - 1);

            return $node;
        }
        $remaining -= 1 + count($node['articles']);
        foreach ($node['children'] as $i => $child) {
            $node['children'][$i] = static::fit($child, $remaining);
        }

        return $node;
    }
}
