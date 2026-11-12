<?php

namespace App\Http\Controllers;

use App\Models\Article;

class ArticleController extends Controller
{
    public function __invoke(string $slug)
    {
        // Staff may preview drafts; visitors only see published articles whose publish date has come.
        $preview = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));

        $article = Article::query()
            ->where('slug', $slug)
            ->when(! $preview, fn ($q) => $q->published())
            ->with(['category', 'extraCategories', 'author', 'images', 'videos', 'chapters.slides'])
            ->firstOrFail();

        $category = $article->category;
        $trail = $category ? CategoryController::trail($category) : collect();

        $same = $category ? Article::published()->where('category_id', $category->id) : null;
        $previous = $same ? (clone $same)->where('id', '<', $article->id)->orderByDesc('id')->first() : null;
        $next = $same ? (clone $same)->where('id', '>', $article->id)->orderBy('id')->first() : null;
        $related = $same ? (clone $same)->where('id', '!=', $article->id)->with('category')->withCount('slides')->orderByDesc('publish_date')->limit(4)->get() : collect();

        // Approved comments for everyone, plus the visitor's own comments that still wait.
        $me = auth()->id();
        $visible = fn ($q) => $q->where(fn ($w) => $w->where('status', 'approved')->when($me, fn ($o) => $o->orWhere(fn ($x) => $x->where('status', 'pending')->where('user_id', $me))));
        $comments = \App\Models\Comment::query()
            ->where('article_id', $article->id)->whereNull('parent_id')
            ->tap($visible)
            ->with(['user', 'replies' => fn ($q) => $visible($q)->with('user')])
            ->orderBy('id')->get();

        $storyItems = \App\Support\StoryItems::build($article);

        return view('article', [
            'storyItems' => $storyItems,
            'comments' => $comments,
            'article' => $article,
            'trail' => $trail,
            'previous' => $previous,
            'next' => $next,
            'related' => $related,
            'preview' => $preview && $article->status !== 'published',
        ]);
    }
}
