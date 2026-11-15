<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Page;
use App\Models\Person;
use App\Models\Story;
use App\Support\Synonyms;
use Illuminate\Http\Request;

/**
 * The site search. It finds a thing by its ID (A-42, C-12, D-9, P-2, K-1, S-7, or an index ID such as QR-1.1.001), by words of its title,
 * by its search keywords, or by words in its text. Every word typed must be found (any capital letters), in the visitor's language
 * or in the main language. Choosing a category limits the search to it and everything below it; a category chosen without a word lists its topics.
 * Nothing is left out: every category, fact, page and contributor that matches is shown, and the articles are shown in full pages with a count.
 */
class SearchController extends Controller
{
    /** Articles on one page of results. "Show all" lifts it (up to ALL_CAP). */
    public const PER_PAGE = 120;

    public const ALL_CAP = 2000;

    /** The kinds of ID: the letter in front of the number. */
    private const LETTERS = ['a' => 'articles', 'c' => 'categories', 'd' => 'facts', 'p' => 'pages', 'k' => 'people'];

    public function __invoke(Request $request)
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 200));
        $categoryId = (int) $request->query('category');
        $showAll = $request->boolean('all');
        $category = $categoryId ? Category::query()->where('is_visible', true)->find($categoryId) : null;

        $result = ['articles' => null, 'subjects' => collect(), 'categories' => collect(), 'facts' => collect(), 'stories' => collect(), 'pages' => collect(), 'people' => collect(), 'cut' => false, 'also' => []];

        if ($q !== '' || $category) {
            $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower($q))));
            $units = Synonyms::units($words);   // what is looked for: each word, with the words that mean the same
            $result['also'] = collect($units)->pluck('also')->flatten()->unique()->values()->all();
            $ids = $category ? $category->descendantIds() : null;

            // ----- articles that are published -----
            $query = Article::published()->with(['category', 'contributor'])->withCount('slides');
            static::applyWords($query, $units, 'a', ['title', 'short_detail', 'long_detail'], ['search_words', 'working_name']);
            if ($ids) {
                $query->where(fn ($w) => $w->whereIn('category_id', $ids)->orWhereHas('extraCategories', fn ($c) => $c->whereIn('categories.id', $ids)));
            }
            static::exactFirst($query, $words, 'a');
            $query->orderByDesc('publish_date')->orderByDesc('id');
            $result['articles'] = $showAll ? $query->paginate(static::ALL_CAP)->withQueryString() : $query->paginate(static::PER_PAGE)->withQueryString();

            // ----- Visual Stories -----
            $stories = Story::published()->with(['category', 'contributor'])->withCount('slides');
            static::applyWords($stories, $units, 's', ['title', 'short_detail', 'long_detail'], ['search_words']);
            if ($ids) {
                $stories->where(fn ($w) => $w->whereIn('category_id', $ids)->orWhereHas('extraCategories', fn ($c) => $c->whereIn('categories.id', $ids)));
            }
            static::exactFirst($stories, $words, 's');
            $result['stories'] = $stories->orderByDesc('publish_date')->orderByDesc('id')->get();

            // ----- planned subjects of the indexes (no article yet), found by their ID or their words -----
            $subjects = Article::query()->where('is_index_line', true)->where('status', '!=', 'published')->where('status', '!=', 'archived')->with('category');
            static::applyWords($subjects, $units, 'a', ['title'], ['working_name']);
            if ($ids) {
                $subjects->whereIn('category_id', $ids);
            }
            static::exactFirst($subjects, $words, 'a');
            $subjects = $subjects->orderBy('category_id')->orderBy('sort_order')->orderBy('id')->limit(static::ALL_CAP + 1)->get()
                ->filter(fn (Article $a) => $a->category && $a->category->is_visible)->values();
            $result['cut'] = $subjects->count() > static::ALL_CAP;
            $result['subjects'] = $subjects->take(static::ALL_CAP);

            // ----- Did You Know? facts -----
            $facts = DidYouKnow::query()->visible();
            static::applyWords($facts, $units, 'd', ['title', 'body'], ['source']);
            if ($ids) {
                $facts->whereIn('category_id', $ids);
            }
            static::exactFirst($facts, $words, 'd');
            $result['facts'] = $facts->ordered()->get();

            if ($q !== '') {
                // ----- categories (hidden ones never) -----
                $categories = Category::query()->where('is_visible', true);
                static::applyWords($categories, $units, 'c', ['name', 'description', 'header_text'], ['working_name']);
                if ($ids) {
                    $categories->whereIn('id', $ids);
                }
                static::exactFirst($categories, $words, 'c');
                $result['categories'] = $categories->orderBy('tree_path')->get();

                // ----- website pages and contributors -----
                if (! $category) {
                    $pages = Page::query()->published();
                    static::applyWords($pages, $units, 'p', ['title', 'body'], []);
                    static::exactFirst($pages, $words, 'p');
                    $result['pages'] = $pages->orderBy('sort_order')->get();

                    $people = Person::query()->where('show_page', true);
                    static::applyWords($people, $units, 'k', [], ['name', 'role_title', 'tradition', 'bio', 'qualification_note']);
                    static::exactFirst($people, $words, 'k');
                    $result['people'] = $people->orderBy('name')->get();
                }
            }
        }

        $total = ($result['articles']?->total() ?? 0) + $result['subjects']->count() + $result['stories']->count() + $result['categories']->count()
            + $result['facts']->count() + $result['pages']->count() + $result['people']->count();

        return view('search', $result + ['q' => $q, 'chosen' => $categoryId, 'within' => $category, 'total' => $total, 'showAll' => $showAll]);
    }

    /** "A-42", "a42" or "A 42" is an ID: the letter says what kind, the number is the id. Null when the word is not an ID. */
    private static function codeToken(string $word): ?array
    {
        return preg_match('/^([acdpks])-?(\d{1,9})$/', $word, $m) ? [$m[1], (int) $m[2]] : null;
    }

    /**
     * Every word typed must be found: in the chosen columns, or (when the word is an ID of this kind) as the id itself.
     * A word with synonyms is found when any of its words is found (Quran, Koran, Qur'an).
     *
     * @param  list<array{typed: string, look_for: list<string>, also: list<string>}>  $units
     */
    private static function applyWords($query, array $units, string $letter, array $translated, array $plain): void
    {
        foreach ($units as $unit) {
            $likes = array_map(fn ($w) => static::like($w), $unit['look_for']);
            $token = count($unit['look_for']) === 1 ? static::codeToken($unit['typed']) : null;
            $query->where(function ($w) use ($likes, $token, $letter, $translated, $plain) {
                $w->where(function ($any) use ($likes, $translated, $plain) {
                    foreach ($likes as $like) {
                        $any->orWhere(fn ($x) => static::matches($x, $translated, $plain, $like));
                    }
                });
                if ($token && $token[0] === $letter) {
                    $w->orWhere('id', $token[1]);
                }
            });
        }
    }

    /** When the search is one ID, the thing with that ID comes first. */
    private static function exactFirst($query, array $words, string $letter): void
    {
        $token = count($words) === 1 ? static::codeToken($words[0]) : null;
        if ($token && $token[0] === $letter) {
            $query->orderByRaw('(id = ?) desc', [$token[1]]);
        }
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
