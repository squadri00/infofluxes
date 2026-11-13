<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use App\Support\Html;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

class ManagePeople extends ManageRecords
{
    protected static string $resource = PersonResource::class;

    protected ?string $heading = 'Contributors';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New contributor')->modalWidth(Width::FiveExtraLarge)
                ->mutateDataUsing(function (array $data) {
                    $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Person::freeSlug((string) $data['name']);
                    $data['page_body'] = Html::clean((string) ($data['page_body'] ?? ''));

                    return $data;
                }),
        ];
    }
}
