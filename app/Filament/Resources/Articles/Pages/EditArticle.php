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

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')->label('Preview on the website')->icon('heroicon-o-eye')->color('gray')
                ->tooltip('Opens the saved article as visitors will see it. Save first to see your latest changes.')
                ->url(fn () => route('article.show', $this->record->slug))->openUrlInNewTab(),
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
