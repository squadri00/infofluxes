<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    /** An empty web address is made from the name, and made unique. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['slug'] ?? null)) {
            $base = Str::slug((string) ($data['name'][config('infofluxes.default_locale')] ?? '')) ?: 'category';
            $slug = $base;
            $n = 2;
            while (\App\Models\Category::withTrashed()->where('slug', $slug)->exists() || in_array($slug, \App\Models\Category::RESERVED_SLUGS, true)) {
                $slug = $base.'-'.$n++;
            }
            $data['slug'] = $slug;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
