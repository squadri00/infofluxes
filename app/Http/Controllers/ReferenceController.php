<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Story;

class ReferenceController extends Controller
{
    /** /id/A-42, /id/C-12 and /id/S-7 go to the page of that article, category or story. */
    public function __invoke(string $code)
    {
        $kind = strtoupper($code[0]);
        $id = (int) substr($code, 2);

        if ($kind === 'S') {
            $story = Story::published()->findOrFail($id);

            return redirect($story->url(), 301);
        }
        if ($kind === 'A') {
            $article = Article::published()->find($id);
            if ($article) {
                return redirect()->route('article.show', $article->slug, 301);
            }
            // An article that became a visual story keeps its old code: A-4008 goes to its story.
            $story = Story::published()->where('custom_data->old_article_id', $id)->first();
            abort_unless($story, 404);

            return redirect($story->url(), 301);
        }
        $category = Category::where('is_visible', true)->findOrFail($id);

        return redirect()->route('category.show', $category->slug, 301);
    }
}