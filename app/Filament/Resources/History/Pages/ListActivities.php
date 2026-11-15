<?php

namespace App\Filament\Resources\History\Pages;

use App\Filament\Resources\History\ActivityResource;
use App\Models\Setting;
use App\Support\History\HistoryKeeper;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected ?string $heading = 'History';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('keep')->label('How long to keep the history')->color('gray')->icon('heroicon-o-cog-6-tooth')
                ->modalHeading('How long to keep the history')
                ->modalDescription('Lines older than this are removed once a week. The history keeps who changed what, so it grows with every change.')
                ->schema([TextInput::make('months')->label('Months')->numeric()->minValue(1)->maxValue(120)->required()->default(fn () => HistoryKeeper::months())])
                ->action(function (array $data) {
                    Setting::put('history_keep_months', max(1, min(120, (int) $data['months'])));
                    Notification::make()->success()->title('Saved.')->body('The history is kept for '.HistoryKeeper::months().' months.')->send();
                }),
        ];
    }
}
