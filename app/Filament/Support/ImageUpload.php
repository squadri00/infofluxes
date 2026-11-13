<?php

namespace App\Filament\Support;

use App\Support\ImageStore;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** An upload box for a picture: any normal picture up to the set size goes in, and a lighter WebP is saved (see App\Support\ImageStore). */
class ImageUpload
{
    public static function make(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->disk(ImageStore::DISK)
            ->directory($directory)
            ->acceptedFileTypes(ImageStore::TYPES)
            ->maxSize(fn () => ImageStore::maxKb())
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => ImageStore::store($file, $directory))
            ->helperText(fn () => 'JPG, PNG or WebP, up to '.(ImageStore::maxKb() / 1024).' MB. It is saved lighter and no wider than '.ImageStore::maxWidth().' pixels.');
    }
}
