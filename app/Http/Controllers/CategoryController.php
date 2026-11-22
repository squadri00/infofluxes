<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;

class CategoryController extends Controller
{
    public const PER_PAGE = 24;

    public function __invoke(\Illuminate\Http\Request $request, string $slug)
    {
        // Staff may look at a hidden category; visitors only see visible ones (and only when every parent above is visible).
        $preview = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
        $category = Category::query()->where('slug', $slug)->when(! $preview, fn ($q) => $q->where('is_visible', true))->firstOrFail();

        $trail = static::trail($category);
        if (! $preview && $trail->contains(fn ($c) => ! $c->is_visible)) {
            abort(404);
        }

        // The Index view: the whole branch as a quick list. It is chosen with ?view=index.
        $mode = $request->query('view') === 'index' ? 'index' : null;
        $index = $mode === 'index' ? \App\Support\CategoryIndex::build($category, $preview) : null;

        $articles = Article::published()
            ->with(['category', 'contributor'])->withCount('slides')
            ->where(fn ($q) => $q->where('category_id', $category->id)->orWhereHas('extraCategories', fn ($c) => $c->where('categories.id', $category->id)))
            ->orderBy('sort_order')->orderByDesc('publish_date')->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        // A section with tabs (for example Stories in pictures): All and one tab for each sub-category, with its own stories.
        $topics = \App\Support\TopicTabs::isOn($category) && $mode !== 'index' ? \App\Support\TopicTabs::build($category, $request->query('topic'), $preview) : null;

        // The "All articles" tab: everything in this category and below it (only for a category that has sub-categories).
        $hasAllTab = $mode !== 'index' && ! $topics && \App\Support\AllArticles::applies($category, $preview);
        $all = $hasAllTab && $request->query('list') === 'all' ? \App\Support\AllArticles::build($category, $request, $preview) : null;

        // The row under the banner: what is starred for this master category, or else its newest items.
        $featured = $mode === 'index' || $topics ? collect() : \App\Support\MasterFeatured::for($category);
        $featuredIsNew = false;
        // (A category that leaves its articles off its page has no "New in" row either.)
        if ($featured->isEmpty() && $mode !== 'index' && ! $topics && ! ($category->custom_data['hide_articles'] ?? false)) {
            $featured = \App\Support\MasterFeatured::newest($category);
            $featuredIsNew = $featured->isNotEmpty();
        }

        return view('category', [
            'hasAllTab' => $hasAllTab,
            'allTotal' => $hasAllTab ? \App\Support\AllArticles::total($category) : 0,
            'all' => $all,
            'category' => $category,
            'trail' => $trail,
            'children' => $category->children()->when(! $preview, fn ($q) => $q->where('is_visible', true))->get(),
            'articles' => $articles,
            'preview' => $preview && ! $category->is_visible,
            'mode' => $mode,
            'index' => $index,
            // The items featured at the top of a master (top-level) category.
            'featured' => $featured,
            'featuredIsNew' => $featuredIsNew,
            // The banner (a master category, or one that chose it): the numbers and the "Start here" button.
            'hero' => \App\Support\CategoryHero::style($category) === 'banner' ? ['picture' => \App\Support\CategoryHero::picture($category), 'stats' => \App\Support\CategoryHero::stats($category), 'start' => \App\Support\CategoryHero::start($category)] : null,
            // The Visual Stories of this category (the main one or an "also in"), and the "Did You Know?" facts that were put on its page.
            'topics' => $topics,
            'stories' => $topics ? $topics['stories']->getCollection() : \App\Models\Story::published()->with(['category', 'contributor'])->withCount('slides')
                ->where(fn ($q) => $q->where('category_id', $category->id)->orWhereHas('extraCategories', fn ($c) => $c->where('categories.id', $category->id)))
                ->orderBy('sort_order')->orderByDesc('publish_date')->orderByDesc('id')->get(),
            'facts' => \App\Models\DidYouKnow::query()->visible()->where('category_id', $category->id)->ordered()->get(),
        ]);
    }

    /** The categories from the top one down to this one. */
    public static function trail(Category $category)
    {
        $trail = collect([$category]);
        $node = $category;
        $guard = 0;
        while ($node->parent_id && $guard++ < 12) {
            $node = Category::withTrashed()->find($node->parent_id);
            if (! $node) {
                break;
            }
            $trail->prepend($node);
        }

        return $trail;
    }
}
