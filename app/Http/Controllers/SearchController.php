<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;

/**
 * The site search. Every word typed must be found (any capital letters) in the title, the short detail, the search words or the long text,
 * in the visitor's language or in the main language. Choosing a category limits the search to it and everything below it.
 * A category chosen without any word lists its topics.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 200));
        $categoryId = (int) $request->query('category');
        $category = $categoryId ? Category::query()->where('is_visible', true)->find($categoryId) : null;
        $articles = null;
        $categories = collect();
        $facts = collect();

        if ($q !== '' || $category) {
            $query = Article::published()->with(['category', 'contributor'])->withCount('slides');

            foreach (array_filter(preg_split('/\s+/u', mb_strtolower($q))) as $word) {
                $like = static::like($word);
                $query->where(fn ($w) => static::matches($w, ['title', 'short_detail', 'long_detail'], ['search_words'], $like));
            }

            if ($category) {
                $ids = $category->descendantIds();
                $query->where(fn ($w) => $w->whereIn('category_id', $ids)->orWhereHas('extraCategories', fn ($c) => $c->whereIn('categories.id', $ids)));
            }

            $articles = $query->orderByDesc('publish_date')->orderByDesc('id')->paginate(20)->withQueryString();

            // The Did You Know? facts follow the same rules: every word, any capital letters, limited to the chosen category.
            $factQuery = \App\Models\DidYouKnow::query()->visible();
            foreach (array_filter(preg_split('/\s+/u', mb_strtolower($q))) as $word) {
                $like = static::like($word);
                $factQuery->where(fn ($w) => static::matches($w, ['title', 'body'], ['source'], $like));
            }
            if ($category) {
                $factQuery->whereIn('category_id', $category->descendantIds());
            }
            $facts = $factQuery->ordered()->limit(24)->get();

            if ($q !== '') {
                $like = static::like(mb_strtolower($q));
                $categories = Category::query()->where('is_visible', true)
                    ->where(fn ($w) => static::matches($w, ['name'], [], $like))
                    ->orderBy('tree_path')->limit(12)->get();
            }
        }

        return view('search', ['q' => $q, 'articles' => $articles, 'categories' => $categories, 'chosen' => $categoryId, 'within' => $category, 'facts' => $facts]);
    }

    /** A word as a LIKE pattern: the characters that mean something to LIKE are made plain (the escape character is the pipe). */
    private static function like(string $word): string
    {
        return '%'.str_replace(['|', '%', '_'], ['||', '|%', '|_'], $word).'%';
    }

    /**
     * One word against some columns, ignoring capital letters.
     * $translated are columns that hold one text per language (read in the visitor's language and in the main one); $plain are ordinary columns.
     */
    private static function matches($where, array $translated, array $plain, string $like): void
    {
        $grammar = $where->getGrammar();
        $locales = array_unique([app()->getLocale(), config('infofluxes.default_locale')]);

        $where->where(function ($w) use ($grammar, $translated, $plain, $like, $locales) {
            foreach ($translated as $column) {
                foreach ($locales as $locale) {
                    $w->orWhereRaw('LOWER('.$grammar->wrap($column.'->'.$locale).") LIKE ? ESCAPE '|'", [$like]);
                }
            }
            foreach ($plain as $column) {
                $w->orWhereRaw('LOWER('.$grammar->wrap($column).") LIKE ? ESCAPE '|'", [$like]);
            }
        });
    }
}
