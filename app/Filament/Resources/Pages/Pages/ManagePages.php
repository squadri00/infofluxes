<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

class ManagePages extends ManageRecords
{
    protected static string $resource = PageResource::class;

    protected ?string $heading = 'Pages';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New page')->modalWidth(Width::SevenExtraLarge)
                ->mutateDataUsing(function (array $data) {
                    $main = config('infofluxes.default_locale');
                    $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Page::uniqueSlug((string) ($data['title'][$main] ?? 'page'));

                    return PageResource::cleanBody($data);
                }),
        ];
    }
}
