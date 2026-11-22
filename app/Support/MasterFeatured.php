<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\Story;

/**
 * The items featured at the top of a master (top-level) category: articles and stories that have the master-category star,
 * from this category or any category below it. Lower order number first (0 means after the numbered ones), then the newest.
 */
class MasterFeatured
{
    public const LIMIT = 3;

    public static function applies(Category $category): bool
    {
        return $category->parent_id === null;
    }

    /** @return \Illuminate\Support\Collection<int, Article|Story> */
    public static function for(Category $master, int $limit = self::LIMIT)
    {
        if (! static::applies($master)) {
            return collect();
        }
        $ids = $master->descendantIds();
        $inside = fn ($q) => $q->whereIn('category_id', $ids)->orWhereHas('extraCategories', fn ($c) => $c->whereIn('categories.id', $ids));

        $items = Article::published()->with('category')->where('is_featured_master', true)->where($inside)->limit(30)->get()
            ->concat(Story::published()->with('category')->where('is_featured_master', true)->where($inside)->limit(30)->get());

        return $items->sortBy(fn ($i) => [$i->featured_master_order == 0 ? 1 : 0, (int) $i->featured_master_order, -1 * (int) ($i->publish_date?->timestamp ?? 0), -1 * (int) $i->id])
            ->take($limit)->values();
    }
}
