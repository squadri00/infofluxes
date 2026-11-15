<?php

namespace App\Filament\Resources\Newsletter;

use App\Filament\Resources\Newsletter\Pages\ManageNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** The newsletter list: who asked for it. Only an address confirmed by a code is "Subscribed". Super user only. */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Newsletter';

    protected static ?string $modelLabel = 'subscriber';

    protected static ?int $navigationSort = 3;

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('settings') ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = NewsletterSubscriber::where('status', 'active')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => NewsletterSubscriber::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['active' => 'success', 'pending' => 'warning', 'unsubscribed' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('language')->label('Language')->toggleable(),
                TextColumn::make('confirmed_at')->label('Subscribed on')->dateTime('j M Y')->sortable(),
                TextColumn::make('created_at')->label('Asked on')->dateTime('j M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('status')->options(NewsletterSubscriber::STATUSES)->default('active')])
            ->recordActions([DeleteAction::make()->label('Delete')->modalDescription('The address is removed from the list for good.')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNewsletterSubscribers::route('/')];
    }
}
