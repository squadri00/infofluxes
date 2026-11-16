<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Support\StoryItems;
use App\Support\StoryMaker\Rules;
use App\Support\StoryMaker\Translations;
use Illuminate\Support\Facades\Cookie;

/**
 * Visual Stories: the gallery of all stories, and the page of one story (the slide viewer first).
 * A story can also be read in its other languages, at /ur/stories/name (and ar, fr, es, hi). A language shows only when it has been switched on.
 * The words around the story (the header and the footer) stay in English; the story and its buttons are in the chosen language.
 */
class StoryController extends Controller
{
    public const PER_PAGE = 24;

    /** The language a visitor chose last, kept in a cookie for a year. */
    public const COOKIE = 'story_lang';

    private function isStaff(): bool
    {
        return auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    public function index()
    {
        $query = Story::published()->with(['category', 'contributor'])->withCount('slides')
            ->orderByDesc('publish_date')->orderByDesc('id');

        // "Stories in: English · اردو": a language shows here only when at least one story has it switched on.
        $names = Rules::languages();
        $counts = [];
        foreach ((clone $query)->get() as $one) {
            foreach (Translations::shown($one) as $code) {
                $counts[$code] = ($counts[$code] ?? 0) + 1;
            }
        }
        $lang = (string) request()->query('lang');
        $lang = isset($counts[$lang]) ? $lang : null;

        if ($lang !== null) {
            $all = $query->get()->filter(fn ($s) => in_array($lang, Translations::shown($s), true))->values();
            $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
            $stories = new \Illuminate\Pagination\LengthAwarePaginator($all->forPage($page, static::PER_PAGE)->values(), $all->count(), static::PER_PAGE, $page, ['path' => request()->url(), 'query' => ['lang' => $lang]]);
            app()->setLocale($lang);
        } else {
            $stories = $query->paginate(static::PER_PAGE);
        }

        $chips = [];
        if ($counts !== []) {
            $chips[] = ['code' => 'en', 'native' => 'English', 'url' => route('stories'), 'current' => $lang === null];
            foreach ($names as $code => $info) {
                if (isset($counts[$code])) {
                    $chips[] = ['code' => $code, 'native' => $info['native'], 'url' => route('stories').'?lang='.$code, 'current' => $lang === $code];
                }
            }
        }

        return view('stories', ['stories' => $stories, 'storyLang' => $lang, 'storyRtl' => $lang !== null && (bool) ($names[$lang]['rtl'] ?? false), 'languageChips' => $chips]);
    }

    /** /ur/stories/name: the route gives the language first, the name second. */
    public function showIn(string $lang, string $slug)
    {
        return $this->show($slug, $lang);
    }

    public function show(string $slug, ?string $lang = null)
    {
        // Staff may preview drafts; visitors only see published stories whose publish date has come.
        $preview = $this->isStaff();

        $story = Story::query()->where('slug', $slug)->when(! $preview, fn ($q) => $q->published())
            ->with(['category', 'extraCategories', 'author', 'contributor', 'chapters.slides'])->firstOrFail();

        $shown = Translations::shown($story);
        $saved = array_keys(Translations::all($story));
        $available = $preview ? $saved : $shown;   // staff can look at a language that is still hidden

        if ($lang === null) {
            // The English address. ?lang=en is the choice "English"; otherwise a language the visitor chose before takes them on.
            if (request()->query('lang') === 'en') {
                Cookie::queue(self::COOKIE, 'en', 60 * 24 * 365);
            } elseif (($remembered = (string) request()->cookie(self::COOKIE)) !== '' && $remembered !== 'en' && in_array($remembered, $shown, true)) {
                return redirect($story->urlIn($remembered));
            }
        } else {
            if (! in_array($lang, $available, true)) {
                return redirect($story->url());
            }
            Cookie::queue(self::COOKIE, $lang, 60 * 24 * 365);
            app()->setLocale($lang);
        }

        if (! $preview) {
            \App\Support\ViewCounter::count($story);
        }

        $category = $story->category;
        $trail = $category ? CategoryController::trail($category) : collect();

        $same = $category ? Story::published()->where('category_id', $category->id) : null;
        $previous = $same ? (clone $same)->where('id', '<', $story->id)->orderByDesc('id')->first() : null;
        $next = $same ? (clone $same)->where('id', '>', $story->id)->orderBy('id')->first() : null;
        $related = Story::published()->where('id', '!=', $story->id)
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->with(['category', 'contributor'])->withCount('slides')->orderByDesc('publish_date')->limit(4)->get();

        // The row "Read in: English · اردو · …" and the language tags for search engines.
        $names = Rules::languages();
        $languageLinks = [];
        $hreflangs = [];
        if ($available !== []) {
            $languageLinks[] = ['code' => 'en', 'native' => 'English', 'url' => $story->url().'?lang=en', 'current' => $lang === null, 'hidden' => false];
            foreach (array_keys($names) as $code) {
                if (in_array($code, $available, true)) {
                    $languageLinks[] = ['code' => $code, 'native' => $names[$code]['native'], 'url' => $story->urlIn($code), 'current' => $lang === $code, 'hidden' => ! in_array($code, $shown, true)];
                }
            }
            if ($shown !== []) {
                $hreflangs[] = ['code' => 'en', 'url' => $story->url()];
                foreach (array_keys($names) as $code) {
                    if (in_array($code, $shown, true)) {
                        $hreflangs[] = ['code' => $code, 'url' => $story->urlIn($code)];
                    }
                }
            }
        }

        return view('story-show', [
            'story' => $story,
            'storyItems' => StoryItems::build($story),
            'trail' => $trail,
            'previous' => $previous,
            'next' => $next,
            'related' => $related,
            'preview' => $preview && $story->status !== 'published',
            'storyLang' => $lang,
            'storyRtl' => $lang !== null && (bool) ($names[$lang]['rtl'] ?? false),
            'languageLinks' => count($languageLinks) > 1 ? $languageLinks : [],
            'hreflangs' => $hreflangs,
            'hiddenLanguage' => $lang !== null && ! in_array($lang, $shown, true),
        ]);
    }
}
