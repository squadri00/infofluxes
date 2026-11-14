<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Support\StoryBook;

class StoryBookController extends Controller
{
    /** /stories/a-story/book gives the PDF book of a story. */
    public function __invoke(string $slug)
    {
        abort_unless(StoryBook::enabled(), 404);

        $preview = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
        $story = Story::query()->where('slug', $slug)->when(! $preview, fn ($q) => $q->published())->whereHas('chapters.slides')->firstOrFail();

        @set_time_limit(120);

        return response(StoryBook::pdf($story), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$story->slug.'.pdf"',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /** The old address, /a-story/book, when it was an article: it goes to the story. */
    public function old(string $slug)
    {
        $story = Story::published()->where('slug', $slug)->orWhere('custom_data->old_slug', $slug)->first();
        abort_unless($story, 404);

        return redirect()->route('story.book', $story->slug, 301);
    }
}