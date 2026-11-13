<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Support\StoryBook;

class StoryBookController extends Controller
{
    /** /an-article/book gives the PDF book of an article that has story slides. */
    public function __invoke(string $slug)
    {
        abort_unless(StoryBook::enabled(), 404);

        $preview = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
        $article = Article::query()->where('slug', $slug)->when(! $preview, fn ($q) => $q->published())->whereHas('chapters.slides')->firstOrFail();

        @set_time_limit(120);

        return response(StoryBook::pdf($article), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$article->slug.'.pdf"',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}