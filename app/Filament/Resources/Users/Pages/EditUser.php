<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // The super user cannot lock themselves out: their own role and status stay as they are.
        if ($this->record->id === auth()->id()) {
            $data['role'] = $this->record->role;
            $data['status'] = $this->record->status;
        }
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
