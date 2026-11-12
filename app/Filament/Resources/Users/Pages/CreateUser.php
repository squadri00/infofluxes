<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** Ticks only matter for a moderator or an author. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! in_array($data['role'] ?? null, ['moderator', 'author'], true)) {
            $data['admin_screens'] = null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
