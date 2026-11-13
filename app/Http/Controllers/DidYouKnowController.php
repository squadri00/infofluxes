<?php

namespace App\Http\Controllers;

use App\Models\DidYouKnow;

/** "Did You Know?": the list of facts, and a page for each fact. */
class DidYouKnowController extends Controller
{
    public function index()
    {
        return view('did-you-know', ['entries' => DidYouKnow::query()->visible()->ordered()->with(['category', 'article'])->get()]);
    }

    public function show(string $slug)
    {
        // Staff may preview a fact that is not published yet; visitors only see published facts that have a source.
        $preview = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));

        $fact = DidYouKnow::query()->where('slug', $slug)->when(! $preview, fn ($q) => $q->visible())->with(['category', 'article'])->firstOrFail();

        $category = $fact->category && $fact->category->is_visible ? $fact->category : null;
        $trail = $category ? CategoryController::trail($category) : collect();

        $more = DidYouKnow::query()->visible()->where('id', '!=', $fact->id)
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->ordered()->limit(4)->get();

        // The left side: the branch the fact belongs to, like an article.
        $sideRoot = $category ? ($category->parent ?? $category) : null;

        return view('did-you-know-show', [
            'sideRoot' => $sideRoot,
            'sideTree' => $sideRoot ? \App\Support\CategoryIndex::build($sideRoot, $preview) : null,
            'sideFacts' => $sideRoot ? DidYouKnow::forBranch($sideRoot) : collect(),
            'fact' => $fact,
            'trail' => $trail,
            'more' => $more,
            'preview' => $preview && ($fact->status !== 'published' || blank($fact->source)),
        ]);
    }
}
