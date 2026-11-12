<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;

/**
 * The Index view of a category page: the whole branch below a category as a quick list of links, like the old index pages.
 * Headings (the categories) open and close, and the subjects (articles) are the lines. A subject that is not published yet but
 * is an index line shows as "details coming". Everything that is hidden stays hidden.
 *
 * A big branch is not drawn in one piece: after $budget lines, the remaining categories are shown as one link line each
 * that opens their own Index. So every page stays light.
 */
class CategoryIndex
{
    /** The most lines (categories and subjects) drawn on one page. */
    public static int $budget = 1500;

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

        $articles = Article::query()
            ->select(['id', 'slug', 'title', 'status', 'publish_date', 'category_id', 'working_name', 'is_index_line', 'sort_order'])
            ->whereIn('category_id', $ids)
            ->where(fn ($q) => $q
                ->where(fn ($p) => $p->where('status', 'published')->where(fn ($d) => $d->whereNull('publish_date')->orWhere('publish_date', '<=', now())))
                ->orWhere(fn ($i) => $i->where('is_index_line', true)->where('status', '!=', 'archived')))
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('category_id');

        $tree = static::fill($tree, $articles, $preview);
        $subjects = $tree['subjects'];
        $remaining = static::$budget;
        $tree = static::fit($tree, $remaining, true);

        return ['root' => $tree, 'subjects' => $subjects];
    }

    /** The code shown beside a heading or a subject: the old index ID (QR-1.1.001) when it has one, else the new code (C-12, A-42). */
    public static function codeOf(object $item): string
    {
        $name = (string) ($item->working_name ?? '');

        return preg_match('/^[A-Z]{2}-[0-9IVX]+(\.[0-9]+)*$/', $name) ? $name : $item->code;
    }

    /** @return array<string, mixed> */
    private static function node(Category $category, $kids): array
    {
        return [
            'category' => $category,
            'children' => ($kids->get($category->id) ?? collect())->map(fn ($c) => static::node($c, $kids))->all(),
            'articles' => [],
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

    /** Put the subjects into their category and work out how big each branch is. */
    private static function fill(array $node, $articles, bool $preview): array
    {
        $lines = [];
        foreach ($articles->get($node['category']->id) ?? [] as $a) {
            $live = $a->status === 'published' && (! $a->publish_date || $a->publish_date->lte(now()));
            $lines[] = ['article' => $a, 'code' => static::codeOf($a), 'title' => $a->title, 'link' => $live || $preview];
        }
        $node['articles'] = $lines;
        $node['children'] = array_map(fn ($c) => static::fill($c, $articles, $preview), $node['children']);
        $node['subjects'] = count($lines) + array_sum(array_column($node['children'], 'subjects'));
        $node['size'] = 1 + count($lines) + array_sum(array_column($node['children'], 'size'));

        return $node;
    }

    /** Draw branches in order while the budget lasts; a branch that no longer fits becomes one link line. */
    private static function fit(array $node, int &$remaining, bool $isRoot = false): array
    {
        if (! $isRoot && $node['size'] > $remaining) {
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
