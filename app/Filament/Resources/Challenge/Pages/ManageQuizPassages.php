<?php

namespace App\Filament\Resources\Challenge\Pages;

use App\Filament\Resources\Challenge\QuizPassageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQuizPassages extends ManageRecords
{
    protected static string $resource = QuizPassageResource::class;

    protected ?string $heading = 'Quran-style test: Arabic passages';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New passage')];
    }
}
