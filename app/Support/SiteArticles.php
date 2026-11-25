<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * "Read all articles": every published article of the site as cards, with a search box, a category choice (a category also covers everything below it),
 * a contributor choice and a sort order. Only categories and contributors that really have a published article are offered.
 */
class SiteArticles
{
    public const PER_PAGE = 24;

    public const SORTS = ['new', 'old', 'az'];

    /** @return array{items: LengthAwarePaginator, q: string, category: ?int, contributor: ?int, sort: string, categories: list<array{id: int, label: string}>, contributors: list<array{id: int, name: string}>, filtered: bool, total: int} */
    public static function build(Request $request): array
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $sort = in_array($request->query('sort'), static::SORTS, true) ? (string) $request->query('sort') : 'new';
        $categories = static::categories();
        $category = (int) $request->query('category') ?: null;
        if ($category && ! in_array($category, array_column($categories, 'id'), true)) {
            $category = null;
        }
        $contributors = static::contributors();
        $contributor = (int) $request->query('contributor') ?: null;
        if ($contributor && ! in_array($contributor, array_column($contributors, 'id'), true)) {
            $contributor = null;
        }

        $query = Article::published()->with(['category', 'contributor'])->withCount('slides');
        if ($q !== '') {
            $query = TextSearch::match($query, ['title', 'short_detail', 'custom_data'], $q);
        }
        if ($category) {
            $ids = Category::find($category)?->descendantIds() ?? [$category];
            $query->where(fn ($w) => $w->whereIn('category_id', $ids)->orWhereHas('extraCategories', fn ($c) => $c->whereIn('categories.id', $ids)));
        }
        if ($contributor) {
            $query->where('contributor_id', $contributor);
        }
        $locale = config('infofluxes.default_locale');
        match ($sort) {
            'old' => $query->orderBy('publish_date')->orderBy('id'),
            'az' => $query->orderBy('title->'.$locale)->orderBy('id'),
            default => $query->orderByDesc('publish_date')->orderByDesc('id'),
        };

        $items = $query->paginate(static::PER_PAGE)->withQueryString();

        return [
            'items' => $items, 'q' => $q, 'category' => $category, 'contributor' => $contributor, 'sort' => $sort,
            'categories' => $categories, 'contributors' => $contributors,
            'filtered' => $q !== '' || $category || $contributor, 'total' => Article::published()->count(),
        ];
    }

    /**
     * The categories of the drop-down: visible ones with at least one published article in them or below them (down to the third level),
     * in the order of the tree, indented by their level, with the number of articles.
     *
     * @return list<array{id: int, label: string}>
     */
    public static function categories(): array
    {
        $visible = Category::query()->where('is_visible', true)->orderBy('tree_path')->get(['id', 'parent_id', 'name', 'slug', 'depth', 'tree_path', 'sort_order']);
        $byParent = $visible->groupBy('parent_id');
        $own = Article::published()->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');
        $total = function (Category $c) use ($byParent, $own, &$total): int {
            $n = (int) ($own[$c->id] ?? 0);
            foreach ($byParent->get($c->id, collect()) as $child) {
                $n += $total($child);
            }

            return $n;
        };

        $out = [];
        $walk = function ($parentId, int $level) use (&$walk, &$out, $byParent, $total) {
            foreach ($byParent->get($parentId, collect())->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $c) {
                $n = $total($c);
                if ($n === 0) {
                    continue;
                }
                if ($level <= 3) {
                    $out[] = ['id' => $c->id, 'label' => str_repeat("\u{2003}", $level - 1).$c->name.' ('.$n.')'];
                    $walk($c->id, $level + 1);
                }
            }
        };
        $walk(null, 1);

        return $out;
    }

    /** @return list<array{id: int, name: string}> the contributors who have a published article, by name */
    public static function contributors(): array
    {
        $ids = Article::published()->whereNotNull('contributor_id')->distinct()->pluck('contributor_id');

        return Person::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => (string) $p->name])->all();
    }
}
