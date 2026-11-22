<?php

namespace App\Filament\Resources\GalleryPictures\Pages;

use App\Filament\Resources\GalleryPictures\GalleryPictureResource;
use App\Support\Gallery;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListGalleryPictures extends ListRecords
{
    protected static string $resource = GalleryPictureResource::class;

    protected ?string $heading = 'Gallery pictures';

    public function mount(): void
    {
        // Every picture of a published page is in the list, with an ID. A picture that is new gets its ID now.
        Gallery::refresh();
        parent::mount();
    }

    public function getSubheading(): ?string
    {
        return 'Every picture of the picture gallery has an ID (I-12) that never changes. Switch a picture off to leave it out of the gallery. It stays on its own page.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')->label('Look for new pictures')->icon('heroicon-o-arrow-path')->color('gray')
                ->action(function () {
                    Gallery::refresh();
                    Notification::make()->success()->title('The list is up to date.')->send();
                }),
            Action::make('open_gallery')->label('Open the gallery')->icon('heroicon-o-arrow-top-right-on-square')->url(fn () => route('gallery'), shouldOpenInNewTab: true),
        ];
    }
}
