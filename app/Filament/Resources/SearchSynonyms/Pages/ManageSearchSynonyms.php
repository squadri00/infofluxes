<?php

namespace App\Filament\Resources\SearchSynonyms\Pages;

use App\Filament\Resources\SearchSynonyms\SearchSynonymResource;
use App\Support\Synonyms;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageSearchSynonyms extends ManageRecords
{
    protected static string $resource = SearchSynonymResource::class;

    protected ?string $heading = 'Search synonyms';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('try')->label('Try a search')->color('gray')->icon('heroicon-o-beaker')
                ->modalHeading('What does the search look for?')
                ->modalDescription('Type what a visitor might type. It shows what the search looks for besides the words typed.')
                ->modalSubmitActionLabel('Show')
                ->schema([TextInput::make('query')->label('Search words')->required()->maxLength(200)])
                ->action(function (array $data) {
                    $also = Synonyms::alsoFor((string) $data['query']);
                    Notification::make()->title($also === [] ? 'Nothing extra: only the words typed are searched.' : 'Also searched for:')
                        ->body($also === [] ? null : implode(', ', $also))->color($also === [] ? 'gray' : 'success')->persistent()->send();
                }),
            CreateAction::make()->label('New group of words'),
        ];
    }
}
