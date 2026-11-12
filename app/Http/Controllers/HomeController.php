<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;

class HomeController extends Controller
{
    /** How many articles each part of the home page shows when the settings say nothing (as on the old site: 6 and 8). */
    public const FEATURED_DEFAULT = 6;

    public const LATEST_DEFAULT = 8;

    public function __invoke()
    {
        $featuredCount = max(0, min(24, (int) Setting::get('home_featured_count', self::FEATURED_DEFAULT)));
        $latestCount = max(0, min(24, (int) Setting::get('home_latest_count', self::LATEST_DEFAULT)));

        $featured = $featuredCount > 0 ? Article::featuredOnHome()->with('category')->withCount('slides')->limit($featuredCount)->get() : collect();

        // An article already shown under "Featured" is not shown again under "Latest".
        $latest = $latestCount > 0
            ? Article::published()->with('category')->withCount('slides')->whereNotIn('id', $featured->pluck('id'))->orderByDesc('publish_date')->orderByDesc('id')->limit($latestCount)->get()
            : collect();

        return view('home', [
            'categories' => Category::query()->whereNull('parent_id')->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->get(),
            'featured' => $featured,
            'articles' => $latest,
        ]);
    }
}