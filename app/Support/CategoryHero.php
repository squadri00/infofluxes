<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Story;

/**
 * The hero at the top of a category page. A master (top-level) category has a banner: its picture behind the title, a strip of numbers
 * (how many sub-categories, articles, stories, pictures and facts it holds, and when it last changed) and two buttons (where to start, and the index).
 * Every other category keeps the split header (the words on the left, the picture on the right). Each category can choose (Organise > Categories).
 */
class CategoryHero
{
    public const STYLES = ['banner', 'split'];

    private static function hidesArticles(Category $category): bool
    {
        return (bool) ($category->custom_data['hide_articles'] ?? false);
    }

    public static function style(Category $category): string
    {
        $chosen = $category->custom_data['hero_style'] ?? null;

        return in_array($chosen, self::STYLES, true) ? $chosen : ($category->parent_id === null ? 'banner' : 'split');
    }

    /**
     * What the strip of numbers shows. A number that is 0 is left out.
     *
     * @return array{items: list<array{key: string, n: int, url: ?string}>, updated: ?\Illuminate\Support\Carbon}
     */
    public static function stats(Category $category): array
    {
        $ids = $category->descendantIds();
        $subs = $category->children()->where('is_visible', true)->count();
        $articles = Article::published()->whereIn('category_id', $ids);
        $stories = Story::published()->whereIn('category_id', $ids);
        $facts = DidYouKnow::visible()->whereIn('category_id', $ids);

        $pictures = 0;
        if (Gallery::on()) {
            $pictures = count(array_filter(Gallery::pictures(), fn ($p) => $p['root'] === $category->id));
        }

        $items = [
            ['key' => 'subcategories', 'n' => $subs, 'url' => null],
            // A category that leaves its articles off its page does not count or name them either.
            ['key' => 'articles', 'n' => static::hidesArticles($category) ? 0 : (clone $articles)->count(), 'url' => null],
            ['key' => 'stories', 'n' => (clone $stories)->count(), 'url' => null],
            ['key' => 'facts', 'n' => (clone $facts)->count(), 'url' => null],
            ['key' => 'pictures', 'n' => $pictures, 'url' => $pictures > 0 && $category->parent_id === null ? route('gallery', ['section' => $category->slug]) : null],
        ];

        $dates = array_filter([(clone $articles)->max('updated_at'), (clone $stories)->max('updated_at'), (clone $facts)->max('updated_at')]);
        $updated = $dates ? \Illuminate\Support\Carbon::parse(max($dates)) : null;

        return ['items' => array_values(array_filter($items, fn ($i) => $i['n'] > 0)), 'updated' => $updated];
    }

    /**
     * The "Start here" button: the first sub-category (in the order of the menu), or else the first article of the category. Null when there is neither.
     *
     * @return array{name: string, url: string}|null
     */
    public static function start(Category $category): ?array
    {
        $first = $category->children()->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->first();
        if ($first) {
            return ['name' => (string) $first->name, 'url' => route('category.show', $first->slug)];
        }
        if (static::hidesArticles($category)) {
            return null;
        }
        $article = Article::published()->where('category_id', $category->id)->orderBy('sort_order')->orderBy('id')->first();

        return $article ? ['name' => $article->mainTitle(), 'url' => route('article.show', $article->slug)] : null;
    }
}
