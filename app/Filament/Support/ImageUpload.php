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
            // The file is named after the page it belongs to (the title or name in the form), see App\Support\PictureNames.
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file, $livewire, \Filament\Schemas\Components\Utilities\Get $get): string => ImageStore::store($file, $directory, \App\Support\PictureNames::fromForm($livewire, $get)))
            ->helperText(fn () => 'JPG, PNG, WebP or GIF, up to '.(ImageStore::maxKb() / 1024).' MB. It is saved as a WebP of at most '.round(ImageStore::maxBytes() / 1024).' KB (and no wider than '.ImageStore::maxWidth().' pixels), so it loads fast, also on a phone. Its file name is made from the title or name of this page, which helps in Google Images.');
    }
}
