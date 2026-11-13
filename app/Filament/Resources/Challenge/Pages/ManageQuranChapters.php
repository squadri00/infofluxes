<?php

namespace App\Filament\Resources\Challenge\Pages;

use App\Filament\Resources\Challenge\QuranChapterResource;
use Filament\Resources\Pages\ManageRecords;

class ManageQuranChapters extends ManageRecords
{
    protected static string $resource = QuranChapterResource::class;

    protected ?string $heading = 'The 114 chapters';
}
