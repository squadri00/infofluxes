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

        $featured = $featuredCount > 0 ? Article::featuredOnHome()->with(['category', 'contributor'])->withCount('slides')->withEngagement()->limit($featuredCount)->get() : collect();

        // An article already shown under "Featured" is not shown again under "Latest".
        $latest = $latestCount > 0
            ? Article::published()->with(['category', 'contributor'])->withCount('slides')->withEngagement()->whereNotIn('id', $featured->pluck('id'))->orderByDesc('publish_date')->orderByDesc('id')->limit($latestCount)->get()
            : collect();

        // The Stories in pictures strip: only the stories that are ticked as featured, up to the number set (0 = no strip).
        $storyCount = max(0, min(12, (int) Setting::get('home_stories_count', 4)));
        $stories = $storyCount > 0
            ? \App\Models\Story::featuredOnHome()->with(['category', 'contributor'])->withCount('slides')->withEngagement()->limit($storyCount)->get()
            : collect();

        return view('home', [
            'stories' => $stories,
            'hero' => \App\Support\Hero::forHome(),
            'categories' => Category::query()->whereNull('parent_id')->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->get(),
            'featured' => $featured,
            'articles' => $latest,
        ]);
    }
}