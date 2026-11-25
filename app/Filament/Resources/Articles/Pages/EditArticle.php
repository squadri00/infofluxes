<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Support\ArticleRules;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ArticleRules::apply($data, $this->record->status, $this->record->slug, (bool) $this->record->ai_assisted, $this->record);
    }

    /** A note under the title when the story of this article was made from an older text. */
    public function getSubheading(): ?string
    {
        $story = $this->record ? \App\Support\StoryMaker\FromArticle::storyOf($this->record) : null;

        return $story && \App\Support\StoryMaker\FromArticle::outdated($story)
            ? 'Its story '.$story->code.' was made before the last change of this text, so it may be out of date. Open the story to read it again.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')->label('Preview on the website')->icon('heroicon-o-eye')->color('gray')
                ->tooltip('Opens the saved article as visitors will see it. Save first to see your latest changes.')
                ->url(fn () => route('article.show', $this->record->slug))->openUrlInNewTab(),
            \App\Support\ScriptExport::button(fn () => $this->record),
            \Filament\Actions\Action::make('picture_rows')->label('Create the picture rows from the tabs')->icon('heroicon-o-photo')->color('gray')
                ->tooltip('Makes one picture row for each tab of the saved text that has none, with a prompt for the image maker. Rows that exist are not changed.')
                ->action(function () {
                    $made = \App\Support\PicturePrompt::sync($this->record->fresh());
                    $this->fillForm();
                    \Filament\Notifications\Notification::make()->success()->title($made > 0 ? $made.' picture row(s) were made.' : 'Every tab already has a picture row.')->send();
                }),
            ...\App\Support\StoryMaker\FromArticleActions::make(fn () => $this->record),
            DeleteAction::make()->label('Delete'),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
