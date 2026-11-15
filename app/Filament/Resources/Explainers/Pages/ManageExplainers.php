<?php

namespace App\Filament\Resources\Explainers\Pages;

use App\Filament\Resources\Explainers\ExplainerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExplainers extends ManageRecords
{
    protected static string $resource = ExplainerResource::class;

    protected ?string $heading = 'Explainers';

    public function getSubheading(): ?string
    {
        return 'A short note about a word, a person or a place. In an article, write @[the word] where it should be tappable. Only published notes are shown to readers.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New explainer')->mutateDataUsing(fn (array $data) => ExplainerResource::applyRules($data)),
        ];
    }
}
