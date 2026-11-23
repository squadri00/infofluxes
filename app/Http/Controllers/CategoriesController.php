<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Support\CategoryHero;
use App\Support\SiteControls;

/**
 * "Browse all topics": every main (top-level) category as a card with its sub-categories (the second level) listed under it.
 * Deeper levels are not listed (a big section has hundreds); each card has a link to its full Index. Hidden categories stay hidden.
 */
class CategoriesController extends Controller
{
    public function __invoke()
    {
        abort_unless(SiteControls::on('categories_page'), 404);

        $visible = Category::query()->where('is_visible', true)->orderBy('tree_path')->get();
        $byParent = $visible->groupBy('parent_id');

        // Published articles in each category (and everything below it), counted once.
        $perCategory = Article::published()->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');
        $total = function (Category $c) use ($byParent, $perCategory, &$total): int {
            $n = (int) ($perCategory[$c->id] ?? 0);
            foreach ($byParent->get($c->id, collect()) as $child) {
                $n += $total($child);
            }

            return $n;
        };

        $masters = [];
        foreach ($byParent->get(null, collect())->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $master) {
            $subs = $byParent->get($master->id, collect())->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
            $masters[] = [
                'category' => $master,
                'picture' => CategoryHero::picture($master),
                'articles' => $total($master),
                'subs' => $subs->map(fn (Category $s) => ['category' => $s, 'articles' => $total($s)])->all(),
            ];
        }

        return view('categories', [
            'masters' => $masters,
            'subTotal' => collect($masters)->sum(fn ($m) => count($m['subs'])),
            'articleTotal' => collect($masters)->sum('articles'),
            'heroImage' => asset(ltrim((string) (\App\Models\Setting::get('categories_hero_image') ?: \App\Models\Setting::get('gallery_hero_image') ?: 'images/gallery-hero.webp'), '/')),
        ]);
    }
}
