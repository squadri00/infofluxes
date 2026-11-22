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
        // Every visit shows the featured parts in a new random order (Site controls > "Shuffle the featured parts of the home page"):
        // when more things are featured than fit, a different choice too. Off: the order numbers set on the items are kept.
        $shuffle = \App\Support\SiteControls::on('home_shuffle');
        $featuredCount = max(0, min(24, (int) Setting::get('home_featured_count', self::FEATURED_DEFAULT)));
        $latestCount = max(0, min(24, (int) Setting::get('home_latest_count', self::LATEST_DEFAULT)));

        $featured = $featuredCount > 0 ? Article::featuredOnHome()->with(['category', 'contributor'])->withCount('slides')->withEngagement()->limit($shuffle ? 60 : $featuredCount)->get() : collect();
        if ($shuffle) {
            $featured = $featured->shuffle()->take($featuredCount)->values();
        }

        // An article already shown under "Featured" is not shown again under "Latest".
        $latest = $latestCount > 0
            ? Article::published()->with(['category', 'contributor'])->withCount('slides')->withEngagement()->whereNotIn('id', $featured->pluck('id'))->orderByDesc('publish_date')->orderByDesc('id')->limit($latestCount)->get()
            : collect();

        // The Stories in pictures strip: only the stories that are ticked as featured, up to the number set (0 = no strip).
        $storyCount = max(0, min(12, (int) Setting::get('home_stories_count', 4)));
        $stories = $storyCount > 0
            ? \App\Models\Story::featuredOnHome()->with(['category', 'contributor'])->withCount('slides')->withEngagement()->limit($shuffle ? 60 : $storyCount)->get()
            : collect();
        if ($shuffle) {
            $stories = $stories->shuffle()->take($storyCount)->values();
        }

        // The "Did You Know?" part: only the facts that are ticked as featured, up to the number set (0 = no part).
        $factCount = max(0, min(12, (int) Setting::get('home_dyk_count', 4)));
        $facts = $factCount > 0 ? \App\Models\DidYouKnow::featuredOnHome()->with('category')->limit($shuffle ? 60 : $factCount)->get() : collect();
        if ($shuffle) {
            $facts = $facts->shuffle()->take($factCount)->values();
        }

        return view('home', [
            'stories' => $stories,
            'facts' => $facts,
            'hero' => \App\Support\Hero::forHome(),
            'categories' => Category::query()->whereNull('parent_id')->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->get(),
            'featured' => $featured,
            'articles' => $latest,
        ]);
    }
}