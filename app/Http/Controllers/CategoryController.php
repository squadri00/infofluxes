<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;

class CategoryController extends Controller
{
    public const PER_PAGE = 24;

    public function __invoke(string $slug)
    {
        // Staff may look at a hidden category; visitors only see visible ones (and only when every parent above is visible).
        $preview = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
        $category = Category::query()->where('slug', $slug)->when(! $preview, fn ($q) => $q->where('is_visible', true))->firstOrFail();

        $trail = static::trail($category);
        if (! $preview && $trail->contains(fn ($c) => ! $c->is_visible)) {
            abort(404);
        }

        $articles = Article::published()
            ->with('category')
            ->where(fn ($q) => $q->where('category_id', $category->id)->orWhereHas('extraCategories', fn ($c) => $c->where('categories.id', $category->id)))
            ->orderBy('sort_order')->orderByDesc('publish_date')->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return view('category', [
            'category' => $category,
            'trail' => $trail,
            'children' => $category->children()->when(! $preview, fn ($q) => $q->where('is_visible', true))->get(),
            'articles' => $articles,
            'preview' => $preview && ! $category->is_visible,
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
