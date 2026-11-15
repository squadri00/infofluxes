<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;

class SitemapController extends Controller
{
    public function __invoke()
    {
        $urls = [[route('home'), null]];
        if (\App\Models\DidYouKnow::published()->whereNotNull('source')->exists()) {
            $urls[] = [route('did-you-know'), \Illuminate\Support\Carbon::parse(\App\Models\DidYouKnow::published()->max('updated_at'))];
        }
        if (\App\Models\Explainer::published()->exists()) {
            $urls[] = [route('words'), \Illuminate\Support\Carbon::parse(\App\Models\Explainer::published()->max('updated_at'))];
        }
        foreach (Category::where('is_visible', true)->orderBy('tree_path')->get() as $c) {
            $urls[] = [route('category.show', $c->slug), $c->updated_at];
        }
        if (\App\Models\Story::published()->exists()) {
            $urls[] = [route('stories'), \Illuminate\Support\Carbon::parse(\App\Models\Story::published()->max('updated_at'))];
        }
        foreach (\App\Models\Story::published()->orderByDesc('id')->limit(5000)->get(['slug', 'updated_at']) as $st) {
            $urls[] = [route('story.show', $st->slug), $st->updated_at];
        }
        foreach (\App\Models\DidYouKnow::visible()->ordered()->get(['slug', 'updated_at']) as $d) {
            $urls[] = [route('did-you-know.show', $d->slug), $d->updated_at];
        }
        foreach (\App\Models\Person::where('show_page', true)->orderBy('name')->get(['slug', 'updated_at']) as $p) {
            $urls[] = [route('people.show', $p->slug), $p->updated_at];
        }
        foreach (\App\Models\Page::published()->where('no_index', false)->orderBy('sort_order')->get(['slug', 'updated_at']) as $p) {
            $urls[] = [route('article.show', $p->slug), $p->updated_at];
        }
        foreach (Article::published()->orderByDesc('id')->limit(5000)->get(['slug', 'updated_at']) as $a) {
            $urls[] = [route('article.show', $a->slug), $a->updated_at];
        }

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }
}
