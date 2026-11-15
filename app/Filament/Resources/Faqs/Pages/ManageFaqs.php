<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Resources\Faqs\FaqResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageFaqs extends ManageRecords
{
    protected static string $resource = FaqResource::class;

    protected ?string $heading = 'FAQ';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New question')->modalWidth(Width::FourExtraLarge)
                ->mutateDataUsing(fn (array $data) => FaqResource::clean($data) + ['sort_order' => (int) \App\Models\Faq::max('sort_order') + 1]),
        ];
    }
}
