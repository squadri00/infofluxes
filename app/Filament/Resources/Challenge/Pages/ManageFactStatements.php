<?php

namespace App\Filament\Resources\Challenge\Pages;

use App\Filament\Resources\Challenge\FactStatementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFactStatements extends ManageRecords
{
    protected static string $resource = FactStatementResource::class;

    protected ?string $heading = 'Fact or myth?: statements';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New statement')];
    }
}
