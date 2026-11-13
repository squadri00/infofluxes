<?php

namespace App\Filament\Resources\DidYouKnow\Pages;

use App\Filament\Resources\DidYouKnow\DidYouKnowResource;
use App\Models\DidYouKnow;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageDidYouKnow extends ManageRecords
{
    protected static string $resource = DidYouKnowResource::class;

    protected ?string $heading = 'Did You Know?';

    public function getSubheading(): ?string
    {
        return 'Short facts, listed like a FAQ. Drag a row to change the order. Only published facts with a source are shown to visitors.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('see')->label('See the page')->icon('heroicon-o-eye')->color('gray')->url(fn () => route('did-you-know'), shouldOpenInNewTab: true),
            CreateAction::make()->label('New fact')->modalWidth(Width::FourExtraLarge)
                ->mutateDataUsing(fn (array $data) => DidYouKnowResource::clean($data) + ['sort_order' => (int) DidYouKnow::max('sort_order') + 1]),
        ];
    }
}
