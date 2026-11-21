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

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')->label('Preview on the website')->icon('heroicon-o-eye')->color('gray')
                ->tooltip('Opens the saved story as visitors will see it. Save first to see your latest changes.')
                ->url(fn () => $this->record->url())->openUrlInNewTab(),
            DeleteAction::make()->label('Delete'),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
