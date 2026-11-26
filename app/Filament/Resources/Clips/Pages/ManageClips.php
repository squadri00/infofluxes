<?php

namespace App\Filament\Resources\Clips\Pages;

use App\Filament\Resources\Clips\ClipResource;
use App\Models\Clip;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageClips extends ManageRecords
{
    protected static string $resource = ClipResource::class;

    protected ?string $heading = 'Clips';

    public function mount(): void
    {
        parent::mount();
        Clip::scan();   // a video put in the clips folder by hand is listed at once
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')->label('Find new videos')->icon('heroicon-o-magnifying-glass')->color('gray')
                ->action(function () {
                    $n = Clip::scan();
                    Notification::make()->title($n > 0 ? $n.' new video'.($n === 1 ? '' : 's').' found' : 'No new videos in the clips folder')->success()->send();
                }),
            CreateAction::make()->label('New clip')->modalWidth(Width::FourExtraLarge)
                ->mutateDataUsing(fn (array $data) => ClipResource::clean($data) + ['sort_order' => (int) Clip::max('sort_order') + 1]),
        ];
    }
}
