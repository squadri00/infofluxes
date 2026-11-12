<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 200));
        $categoryId = (int) $request->query('category');
        $articles = null;
        $categories = collect();

        if ($q !== '') {
            // Every word must be found somewhere in the title, the short detail, the search words or the long text.
            $words = array_filter(preg_split('/\s+/u', $q));
            $query = Article::published()->with('category');
            foreach ($words as $word) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%';
                $query->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('short_detail', 'like', $like)->orWhere('search_words', 'like', $like)->orWhere('long_detail', 'like', $like));
            }
            if ($categoryId) {
                $ids = Category::find($categoryId)?->descendantIds() ?? [$categoryId];
                $query->where(fn ($w) => $w->whereIn('category_id', $ids)->orWhereHas('extraCategories', fn ($c) => $c->whereIn('categories.id', $ids)));
            }
            $articles = $query->orderByDesc('publish_date')->orderByDesc('id')->paginate(20)->withQueryString();

            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $categories = Category::query()->where('is_visible', true)->where('name', 'like', $like)->orderBy('tree_path')->limit(12)->get();
        }

        return view('search', ['q' => $q, 'articles' => $articles, 'categories' => $categories, 'chosen' => $categoryId]);
    }
}
