<?php

namespace App\Filament\Resources\Stories\Pages;

use App\Filament\Resources\Stories\StoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStories extends ListRecords
{
    protected static string $resource = StoryResource::class;

    protected ?string $heading = 'Visual Stories';

    public function getSubheading(): ?string
    {
        return 'Picture stories told in chapters and slides. Each one has its own ID (S-1, S-2...) and its own page.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New story')];
    }
}
