<?php

namespace App\Support\StoryMaker;

use App\Filament\Pages\StoryMaker;
use App\Filament\Resources\Stories\StoryResource;
use App\Models\Article;
use Filament\Actions\Action;

/** The buttons "Make a story from this article" and "Open its story", on the list of articles and on the edit page of an article. */
class FromArticleActions
{
    /** @return list<Action> */
    public static function make(?\Closure $article = null): array
    {
        $get = fn (?Article $record = null): ?Article => $article ? $article() : $record;

        return [
            Action::make('make_story')->label('Make a story from this article')->icon('heroicon-o-photo')->color('gray')
                ->tooltip('Opens the Story maker with this article in it: a short story, made only from the article, with its pictures.')
                ->visible(function (?Article $record = null) use ($get) {
                    $a = $get($record);

                    return $a && FromArticle::canMake($a, auth()->user()) && ! FromArticle::storyOf($a);
                })
                ->url(function (?Article $record = null) use ($get) {
                    return StoryMaker::getUrl(['article' => $get($record)?->id]);
                }),
            Action::make('open_story')->label(function (?Article $record = null) use ($get) {
                $s = ($a = $get($record)) ? FromArticle::storyOf($a) : null;

                return $s ? 'Its story '.$s->code.' ('.$s->status.')' : 'Its story';
            })->icon('heroicon-o-photo')->color('gray')
                ->visible(function (?Article $record = null) use ($get) {
                    $a = $get($record);

                    return $a && FromArticle::storyOf($a) && (auth()->user()?->canUseScreen('storymaker') ?? false);
                })
                ->url(function (?Article $record = null) use ($get) {
                    $s = ($a = $get($record)) ? FromArticle::storyOf($a) : null;

                    return $s ? StoryResource::getUrl('edit', ['record' => $s]) : null;
                }),
        ];
    }
}
