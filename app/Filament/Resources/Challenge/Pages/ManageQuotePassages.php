<?php

namespace App\Filament\Resources\Challenge\Pages;

use App\Filament\Resources\Challenge\QuotePassageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQuotePassages extends ManageRecords
{
    protected static string $resource = QuotePassageResource::class;

    protected ?string $heading = 'Who said it?: quotes';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New quote')];
    }
}
