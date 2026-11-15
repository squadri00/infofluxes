<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Support\StoryItems;

/** Visual Stories: the gallery of all stories, and the page of one story (the slide viewer first). */
class StoryController extends Controller
{
    public const PER_PAGE = 24;

    private function isStaff(): bool
    {
        return auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    public function index()
    {
        $stories = Story::published()->with(['category', 'contributor'])->withCount('slides')
            ->orderByDesc('publish_date')->orderByDesc('id')->paginate(static::PER_PAGE);

        return view('stories', ['stories' => $stories]);
    }

    public function show(string $slug)
    {
        // Staff may preview drafts; visitors only see published stories whose publish date has come.
        $preview = $this->isStaff();

        $story = Story::query()->where('slug', $slug)->when(! $preview, fn ($q) => $q->published())
            ->with(['category', 'extraCategories', 'author', 'contributor', 'chapters.slides'])->firstOrFail();

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

        return view('story-show', [
            'story' => $story,
            'storyItems' => StoryItems::build($story),
            'trail' => $trail,
            'previous' => $previous,
            'next' => $next,
            'related' => $related,
            'preview' => $preview && $story->status !== 'published',
        ]);
    }
}
