<?php

namespace App\Filament\Resources\Challenge\Pages;

use App\Filament\Resources\Challenge\ScripturePassageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageScripturePassages extends ManageRecords
{
    protected static string $resource = ScripturePassageResource::class;

    protected ?string $heading = 'Which scripture?: passages';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New passage')];
    }
}
