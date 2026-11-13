<?php

namespace App\Filament\Resources\Social\Pages;

use App\Filament\Resources\Social\SocialLinkResource;
use App\Models\SocialLink;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSocialLinks extends ManageRecords
{
    protected static string $resource = SocialLinkResource::class;

    protected ?string $heading = 'Social media';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New account')
                ->mutateDataUsing(function (array $data) {
                    // A new account goes to the end of the list unless an order was typed.
                    if (! filled($data['sort_order'] ?? null) || (int) $data['sort_order'] === 0) {
                        $data['sort_order'] = (int) SocialLink::query()->max('sort_order') + 1;
                    }

                    return $data;
                }),
        ];
    }
}
