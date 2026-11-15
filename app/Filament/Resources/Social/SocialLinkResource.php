<?php

namespace App\Filament\Resources\Social;

use App\Filament\Resources\Social\Pages\ManageSocialLinks;
use App\Models\SocialLink;
use App\Support\Social;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Social media: the site's own accounts, shown as round buttons in the footer. */
class SocialLinkResource extends Resource
{
    protected static ?string $model = SocialLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Social media';

    protected static ?string $modelLabel = 'social account';

    protected static ?int $navigationSort = 4;

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
        return static::allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform')->label('Network')->options(Social::options())->required()->native(true),
            TextInput::make('url')->label('Web address of your account')->helperText('Starts with https:// (or mailto: for an email address).')->required()->maxLength(2048)
                ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                    if (Social::safeUrl($value) === null) {
                        $fail('Use a full web address that starts with https:// (or mailto:).');
                    }
                }]),
            TextInput::make('label')->label('Name for the hover hint (optional)')->helperText('Left empty, the name of the network is used.')->maxLength(120),
            Toggle::make('is_active')->label('Shown on the site')->default(true),
            TextInput::make('sort_order')->label('Order (small numbers first)')->numeric()->default(0),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => '['.$state.']')->sortable(),
                TextColumn::make('platform')->label('Network')->formatStateUsing(fn ($state) => Social::PLATFORMS[$state][0] ?? $state)->sortable(),
                TextColumn::make('url')->label('Web address')->limit(60)->url(fn (SocialLink $r) => Social::safeUrl($r->url), true),
                IconColumn::make('is_active')->label('Shown')->boolean(),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the account'),
                ReplicateAction::make()->label('Clone'),
                DeleteAction::make()->label('Delete'),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSocialLinks::route('/')];
    }
}
