<?php

namespace App\Filament\Resources\Languages;

use App\Filament\Resources\Languages\Pages\ManageLanguages;
use App\Models\Language;
use App\Support\Languages;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Languages: the list in the language button of the site, each with its flag. English is the main language and always on.
 * Another language can be added now and set to "Coming soon" (it is shown, greyed out); when its words are ready, switch it On.
 * Only the super user.
 */
class LanguageResource extends Resource
{
    protected static ?string $model = Language::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Languages';

    protected static ?string $modelLabel = 'language';

    protected static ?int $navigationSort = 9;

    private static function allowed(): bool
    {
        return auth()->user()?->isSuperuser() ?? false;
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }
    public static function canEdit(Model $record): bool { return static::allowed(); }
    public static function canDelete(Model $record): bool { return static::allowed() && ! $record->is_default; }
    public static function canDeleteAny(): bool { return static::allowed(); }

    public static function form(Schema $schema): Schema
    {
        $catalog = Languages::data()['catalog'];
        $flags = Languages::data()['flags'];

        return $schema->components([
            Select::make('preset')->label('Pick from the list (fills the boxes below)')->dehydrated(false)->native(false)->searchable()
                ->options(collect($catalog)->mapWithKeys(fn ($row, $code) => [$code => $row[0].' – '.$row[1]])->all())
                ->live()
                ->afterStateUpdated(function ($state, Set $set) use ($catalog) {
                    if ($row = $catalog[$state] ?? null) {
                        $set('code', $state);
                        $set('name', $row[0]);
                        $set('native_name', $row[1]);
                        $set('flag', $row[2]);
                        $set('is_rtl', $row[3]);
                    }
                })
                ->hiddenOn('edit'),
            TextInput::make('code')->label('Code')->required()->maxLength(12)->alphaDash()->unique(ignoreRecord: true)
                ->helperText('The short code, for example ar, ur, fr. It must match the folder of the words in lang/.')
                ->disabled(fn (?Language $record) => (bool) $record?->is_default)->dehydrated(),
            TextInput::make('name')->label('Name in English')->required()->maxLength(80),
            TextInput::make('native_name')->label('Name in its own language')->required()->maxLength(80)
                ->helperText('This is what visitors see in the language button.'),
            Select::make('flag')->label('Flag')->required()->native(false)->searchable()->default('un')
                ->options(collect($flags)->map(fn ($name, $code) => $name)->all())
                ->helperText('A flag is only a picture to help the eye. Pick the one your readers expect.'),
            Toggle::make('is_rtl')->label('Written from right to left (Arabic, Urdu, Persian, Hebrew)'),
            Select::make('status')->label('Status')->options(Language::STATUSES)->default('coming_soon')->required()->native(true)
                ->disabled(fn (?Language $record) => (bool) $record?->is_default)->dehydrated()
                ->helperText('Keep "Coming soon" until the texts of the site are translated.'),
            TextInput::make('sort_order')->label('Order in the list')->numeric()->default(10),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('flag')->label('Flag')->getStateUsing(fn (Language $r) => $r->flagUrl())->width(28)->height(19),
                TextColumn::make('name')->label('Language')->searchable(),
                TextColumn::make('native_name')->label('In its own language'),
                TextColumn::make('code')->label('Code')->badge()->color('gray'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => match ($state) { 'active' => 'On', 'coming_soon' => 'Coming soon', default => 'Off' })
                    ->color(fn ($state) => match ($state) { 'active' => 'success', 'coming_soon' => 'warning', default => 'gray' }),
                TextColumn::make('is_default')->label('Main')->formatStateUsing(fn ($state) => $state ? 'Main language' : ''),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the language'),
                DeleteAction::make()->label('Delete')->visible(fn (Language $r) => ! $r->is_default),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLanguages::route('/')];
    }
}
