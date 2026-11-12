<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;

class ReferenceController extends Controller
{
    /** /id/A-42 and /id/C-12 go to the page of that article or category. */
    public function __invoke(string $code)
    {
        $kind = strtoupper($code[0]);
        $id = (int) substr($code, 2);

        if ($kind === 'A') {
            $article = Article::published()->findOrFail($id);

            return redirect()->route('article.show', $article->slug, 301);
        }
        $category = Category::where('is_visible', true)->findOrFail($id);

        return redirect()->route('category.show', $category->slug, 301);
    }
}
