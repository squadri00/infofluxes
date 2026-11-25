<?php

namespace App\Filament\Resources\Stories\Pages;

use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Support\StoryRules;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditStory extends EditRecord
{
    protected static string $resource = StoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return StoryRules::apply($data, $this->record->status, $this->record->slug, (bool) $this->record->ai_assisted);
    }

    /** A note under the title when the article this story was made from has changed since. */
    public function getSubheading(): ?string
    {
        $article = $this->record?->article_id ? $this->record->article : null;

        return $article && \App\Support\StoryMaker\FromArticle::outdated($this->record)
            ? 'The article '.$article->code.' changed after this story was made, so the story may be out of date. Read it again, or make it again from the article.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('open_article')->label(fn () => 'The article '.$this->record->article?->code)->icon('heroicon-o-document-text')->color('gray')
                ->tooltip('The article this story was made from.')
                ->visible(fn () => (bool) $this->record->article_id && $this->record->article)
                ->url(fn () => \App\Filament\Resources\Articles\ArticleResource::getUrl('edit', ['record' => $this->record->article])),
            \Filament\Actions\Action::make('make_again')->label('Make it again from the article')->icon('heroicon-o-arrow-path')->color('warning')
                ->tooltip('Opens the Story maker with the changed article. The old story stays until you delete it.')
                ->visible(fn () => $this->record->article_id && \App\Support\StoryMaker\FromArticle::outdated($this->record) && \App\Support\StoryMaker\FromArticle::canMake($this->record->article, auth()->user()))
                ->url(fn () => \App\Filament\Pages\StoryMaker::getUrl(['article' => $this->record->article_id])),
            \Filament\Actions\Action::make('preview')->label('Preview on the website')->icon('heroicon-o-eye')->color('gray')
                ->tooltip('Opens the saved story as visitors will see it. Save first to see your latest changes.')
                ->url(fn () => $this->record->url())->openUrlInNewTab(),
            \App\Support\ScriptExport::button(fn () => $this->record),
            DeleteAction::make()->label('Delete'),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
