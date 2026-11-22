<?php

namespace App\Filament\Resources\GalleryPictures;

use App\Filament\Resources\GalleryPictures\Pages\ListGalleryPictures;
use App\Models\GalleryPicture;
use App\Support\Gallery;
use App\Support\Thumbnail;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Gallery pictures: every picture of the picture gallery with its ID (I-12). Here a moderator or the super user switches a picture on or off
 * in the gallery. The picture stays on its own page: only the gallery leaves it out.
 */
class GalleryPictureResource extends Resource
{
    protected static ?string $model = GalleryPicture::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Organise';

    protected static ?string $navigationLabel = 'Gallery pictures';

    protected static ?string $modelLabel = 'gallery picture';

    protected static ?string $pluralModelLabel = 'gallery pictures';

    protected static ?string $slug = 'gallery-pictures';

    protected static ?int $navigationSort = 4;

    private static function allowed(): bool
    {
        return auth()->user()?->canPublish() ?? false;
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        $kinds = ['story' => 'Story', 'article' => 'Article', 'fact' => 'Fact', 'category' => 'Category'];

        return $table
            ->defaultSort('id')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                ImageColumn::make('thumb')->label('Picture')->state(fn (GalleryPicture $r) => Thumbnail::url($r->path))
                    ->imageWidth(96)->imageHeight(64)->extraImgAttributes(['loading' => 'lazy', 'style' => 'object-fit:cover;border-radius:.35rem']),
                TextColumn::make('id')->label('ID')->sortable()->weight('bold')->formatStateUsing(fn ($state) => 'I-'.$state)->copyable()
                    ->searchable(query: fn (Builder $query, string $search) => preg_match('/^i?-?\s*(\d+)$/i', trim($search), $m) ? $query->orWhere('id', (int) $m[1]) : $query),
                TextColumn::make('title')->label('Picture of')->searchable()->wrap()->limit(70)
                    ->description(fn (GalleryPicture $r) => $r->parent !== $r->title ? $r->parent : null),
                TextColumn::make('kind')->label('Kind')->badge()->formatStateUsing(fn ($state) => $kinds[$state] ?? $state)
                    ->color(fn ($state) => ['story' => 'info', 'article' => 'success', 'fact' => 'warning', 'category' => 'gray'][$state] ?? 'gray'),
                ToggleColumn::make('is_shown')->label('Shown in the gallery'),
                IconColumn::make('present')->label('On a published page')->boolean(),
                TextColumn::make('path')->label('File')->searchable()->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kind')->label('Kind')->options($kinds),
                TernaryFilter::make('is_shown')->label('Shown in the gallery')->trueLabel('Shown')->falseLabel('Switched off'),
                TernaryFilter::make('present')->label('On a published page')->trueLabel('Yes')->falseLabel('No (the page is not published any more)')->default(true),
            ])
            ->recordActions([
                Action::make('open')->label('Open its page')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')->url(fn (GalleryPicture $r) => $r->url, shouldOpenInNewTab: true),
                Action::make('picture')->label('View picture')->icon('heroicon-o-eye')->color('gray')->url(fn (GalleryPicture $r) => asset('storage/'.$r->path), shouldOpenInNewTab: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('show')->label('Show in the gallery')->icon('heroicon-o-eye')->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_shown' => true])),
                    BulkAction::make('hide')->label('Leave out of the gallery')->icon('heroicon-o-eye-slash')->color('danger')->requiresConfirmation()->deselectRecordsAfterCompletion()
                        ->modalDescription('The pictures stay on their pages. Only the gallery leaves them out. You can show them again here.')
                        ->action(fn (Collection $records) => $records->each->update(['is_shown' => false])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListGalleryPictures::route('/')];
    }
}
