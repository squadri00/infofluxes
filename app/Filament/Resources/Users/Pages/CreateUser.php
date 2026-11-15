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

    /** The super user vouches for a person they add, so that person is not asked to confirm an email address. */
    protected function afterCreate(): void
    {
        $this->record->forceFill(['email_verified_at' => now()])->save();   // not open to mass assignment, so set on its own
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
