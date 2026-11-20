<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The "All articles" tab of a category: every published article and Visual Story in the category and in all the sub-categories below it,
 * with a search box, a sort choice and one chip for each sub-category. It is chosen with ?list=all.
 */
class AllArticles
{
    public const PER_PAGE = 12;

    private static function inCategories(array $ids): \Closure
    {
        return fn ($q) => $q->whereIn('category_id', $ids)->orWhereHas('extraCategories', fn ($c) => $c->whereIn('categories.id', $ids));
    }

    /** How many published articles and stories are in the category and everything below it. */
    public static function total(Category $category): int
    {
        $ids = $category->descendantIds();

        return Article::published()->where(static::inCategories($ids))->count() + Story::published()->where(static::inCategories($ids))->count();
    }

    /** The tab only makes sense for a category with sub-categories (a last-level category already lists its own articles). */
    public static function applies(Category $category, bool $preview): bool
    {
        return $category->children()->when(! $preview, fn ($q) => $q->where('is_visible', true))->exists();
    }

    /**
     * @return array{items: LengthAwarePaginator, chips: list<array{slug: ?string, name: string, count: int, current: bool, url: string}>, q: string, sort: string, total: int}
     */
    public static function build(Category $category, Request $request, bool $preview): array
    {
        $children = $category->children()->when(! $preview, fn ($q) => $q->where('is_visible', true))->orderBy('sort_order')->orderBy('id')->get();
        $q = trim((string) $request->query('q', ''));
        $q = mb_substr($q, 0, 80);
        $sort = in_array($request->query('sort'), ['old', 'az'], true) ? $request->query('sort') : 'new';
        $current = $request->query('sub') ? $children->firstWhere('slug', $request->query('sub')) : null;

        $fetch = function (array $ids) use ($q) {
            $like = fn ($query) => $q === '' ? $query : \App\Support\TextSearch::match($query, ['title', 'short_detail', 'custom_data'], $q);
            $articles = $like(Article::published()->with(['category', 'contributor'])->withCount('slides')->where(static::inCategories($ids)))->get()->each(fn ($i) => $i->setAttribute('kind', 'article'));
            $stories = $like(Story::published()->with(['category', 'contributor'])->withCount('slides')->where(static::inCategories($ids)))->get()->each(fn ($i) => $i->setAttribute('kind', 'story'));

            return $articles->concat($stories);
        };

        $base = [];
        if ($q !== '') {
            $base['q'] = $q;
        }
        if ($sort !== 'new') {
            $base['sort'] = $sort;
        }
        $url = fn (?string $sub) => route('category.show', array_filter(['slug' => $category->slug, 'list' => 'all', 'sub' => $sub] + $base));

        $chips = [['slug' => null, 'name' => __('site.tab_all'), 'count' => $fetch($category->descendantIds())->count(), 'current' => $current === null, 'url' => $url(null)]];
        foreach ($children as $child) {
            $n = $fetch($child->descendantIds())->count();
            if ($n === 0 && $current?->id !== $child->id) {
                continue;
            }
            $chips[] = ['slug' => $child->slug, 'name' => $child->name, 'count' => $n, 'current' => $current?->id === $child->id, 'url' => $url($child->slug)];
        }

        $all = $fetch(($current ?? $category)->descendantIds());
        $date = fn ($i) => $i->publish_date?->timestamp ?? $i->created_at?->timestamp ?? 0;
        $all = match ($sort) {
            'old' => $all->sortBy(fn ($i) => [$date($i), $i->id])->values(),
            'az' => $all->sortBy(fn ($i) => mb_strtolower((string) $i->title))->values(),
            default => $all->sortByDesc(fn ($i) => [$date($i), $i->id])->values(),
        };

        $page = max(1, (int) $request->query('page', 1));
        $items = new LengthAwarePaginator($all->forPage($page, static::PER_PAGE)->values(), $all->count(), static::PER_PAGE, $page, ['path' => route('category.show', $category->slug)]);
        $items->appends(array_filter(['list' => 'all', 'sub' => $current?->slug] + $base));

        return ['items' => $items, 'chips' => $chips, 'q' => $q, 'sort' => $sort, 'total' => static::total($category)];
    }
}
