<?php

namespace App\Filament\Resources\AiPrompts\Pages;

use App\Filament\Resources\AiPrompts\AiPromptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAiPrompts extends ManageRecords
{
    protected static string $resource = AiPromptResource::class;

    protected ?string $heading = 'AI prompts';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New specialist prompt')->modalWidth('5xl')
                ->mutateDataUsing(function (array $data) {
                    $data['kind'] = 'specialist';
                    $data['slug'] = 'own-'.\Illuminate\Support\Str::slug($data['title']).'-'.substr(md5((string) microtime(true)), 0, 5);

                    return $data;
                }),
        ];
    }
}
