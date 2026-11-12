<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;

class SitemapController extends Controller
{
    public function __invoke()
    {
        $urls = [[route('home'), null]];
        foreach (Category::where('is_visible', true)->orderBy('tree_path')->get() as $c) {
            $urls[] = [route('category.show', $c->slug), $c->updated_at];
        }
        foreach (Article::published()->orderByDesc('id')->limit(5000)->get(['slug', 'updated_at']) as $a) {
            $urls[] = [route('article.show', $a->slug), $a->updated_at];
        }

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }
}
