<?php

namespace App\Filament\Resources\Languages\Pages;

use App\Filament\Resources\Languages\LanguageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLanguages extends ManageRecords
{
    protected static string $resource = LanguageResource::class;

    protected ?string $heading = 'Languages';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a language')];
    }
}
