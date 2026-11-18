<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Story;

/**
 * The tabs of a section that has grown big (for example "Stories in pictures"): All, then one tab for each sub-category.
 * A category becomes a section with tabs when its switch "Show its sub-categories as tabs" is on. A tab is chosen with ?topic=the-slug of the sub-category.
 */
class TopicTabs
{
    public const PER_PAGE = 12;

    public static function isOn(Category $category): bool
    {
        return (bool) ($category->custom_data['topic_tabs'] ?? false);
    }

    private static function inCategories(array $ids): \Closure
    {
        return fn ($q) => $q->whereIn('category_id', $ids)->orWhereHas('extraCategories', fn ($c) => $c->whereIn('categories.id', $ids));
    }

    /**
     * @return array{tabs: list<array{slug: ?string, name: string, count: int, current: bool, url: string}>, stories: \Illuminate\Pagination\LengthAwarePaginator, current: ?Category}
     */
    public static function build(Category $category, ?string $topic, bool $preview): array
    {
        $children = $category->children()->when(! $preview, fn ($q) => $q->where('is_visible', true))->orderBy('sort_order')->orderBy('id')->get();
        $count = fn (array $ids) => Story::published()->where(static::inCategories($ids))->count();

        $current = $topic !== null ? $children->firstWhere('slug', $topic) : null;
        $tabs = [[
            'slug' => null, 'name' => __('site.tab_all'), 'count' => $count($category->descendantIds()), 'current' => $current === null,
            'url' => route('category.show', $category->slug),
        ]];
        foreach ($children as $child) {
            $n = $count($child->descendantIds());
            if ($n === 0 && $current?->id !== $child->id) {
                continue;
            }
            $tabs[] = ['slug' => $child->slug, 'name' => $child->name, 'count' => $n, 'current' => $current?->id === $child->id, 'url' => route('category.show', ['slug' => $category->slug, 'topic' => $child->slug])];
        }

        $ids = ($current ?? $category)->descendantIds();
        $stories = Story::published()->with(['category', 'contributor'])->withCount('slides')
            ->where(static::inCategories($ids))
            ->orderBy('sort_order')->orderByDesc('publish_date')->orderByDesc('id')
            ->paginate(static::PER_PAGE)->appends($current ? ['topic' => $current->slug] : []);

        return ['tabs' => $tabs, 'stories' => $stories, 'current' => $current];
    }
}
