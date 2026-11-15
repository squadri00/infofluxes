<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Redirects: an old web address that leads to a new one. The site makes them by itself when the address of an article, a story,
 * a category or a page is changed. Here you can also add one by hand, switch one off, or remove one. Only the super user.
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Redirects';

    protected static ?string $modelLabel = 'redirect';

    protected static ?int $navigationSort = 8;

    private static function allowed(): bool
    {
        return auth()->user()?->isSuperuser() ?? false;
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }
    public static function canEdit(Model $record): bool { return static::allowed(); }
    public static function canDelete(Model $record): bool { return static::allowed(); }
    public static function canDeleteAny(): bool { return static::allowed(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')->label('Old address')->required()->maxLength(500)->prefix('…/')
                ->helperText('Only the part after the site name, for example old-address or category/old-name. Nothing after a "?".')
                ->formatStateUsing(fn ($state) => ltrim((string) $state, '/'))
                ->dehydrateStateUsing(fn ($state) => Redirect::normalize((string) $state))
                ->rules([fn (?Redirect $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                    $path = Redirect::normalize((string) $value);
                    if ($path === '/') {
                        $fail('The home page cannot be redirected.');
                    }
                    if (Redirect::isNever($path)) {
                        $fail('Addresses of the admin and the technical parts of the site cannot be redirected.');
                    }
                    $same = Redirect::query()->where('from_key', mb_strtolower($path))->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists();
                    if ($same) {
                        $fail('There is already a redirect from this address.');
                    }
                }]),
            TextInput::make('to')->label('Goes to')->required()->maxLength(1000)
                ->helperText('An address on this site, such as new-address or category/new-name, or a full address that starts with https://')
                ->formatStateUsing(fn ($state) => Redirect::isExternal((string) $state) ? $state : ltrim((string) $state, '/'))
                ->dehydrateStateUsing(fn ($state) => Redirect::isExternal((string) $state) ? trim((string) $state) : Redirect::normalize((string) $state))
                ->rules([fn (\Filament\Schemas\Components\Utilities\Get $get, ?Redirect $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                    if (Redirect::isExternal((string) $value)) {
                        return;
                    }
                    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', trim((string) $value))) {
                        $fail('Use an address on this site, or a full address that starts with https://.');

                        return;
                    }
                    $from = mb_strtolower(Redirect::normalize((string) $get('from_path')));
                    $to = mb_strtolower(Redirect::normalize((string) $value));
                    if ($from === $to) {
                        $fail('The old and the new address are the same.');

                        return;
                    }
                    // Would this make a circle? Follow the new address: it must not lead back to the old one.
                    $key = $to;
                    for ($i = 0; $i < Redirect::MAX_HOPS; $i++) {
                        $next = Redirect::query()->where('from_key', $key)->where('is_active', true)->when($record, fn ($q) => $q->whereKeyNot($record->id))->first();
                        if (! $next || Redirect::isExternal($next->to)) {
                            return;
                        }
                        $key = mb_strtolower(Redirect::normalize($next->to));
                        if ($key === $from) {
                            $fail('This would make a circle: the new address leads back to the old one.');

                            return;
                        }
                    }
                }]),
            Select::make('code')->label('Kind')->options([301 => 'Moved for good (301): search engines follow it and forget the old address', 302 => 'Moved for now (302): the old address stays known'])->default(301)->required()->native(true),
            TextInput::make('note')->label('Note (only for you)')->maxLength(255),
            Toggle::make('is_active')->label('On')->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('from_path')->label('Old address')->searchable()->limit(60)->copyable(),
                TextColumn::make('to')->label('Goes to')->searchable()->limit(60),
                TextColumn::make('type')->label('Made')->badge()->formatStateUsing(fn ($state) => $state === 'auto' ? 'By itself' : 'By hand')->color(fn ($state) => $state === 'auto' ? 'gray' : 'info'),
                TextColumn::make('code')->label('Kind')->formatStateUsing(fn ($state) => $state === 302 ? 'For now' : 'For good'),
                TextColumn::make('hits')->label('Used')->sortable()->suffix(' times'),
                TextColumn::make('last_hit_at')->label('Last used')->since()->placeholder('never')->sortable(),
                ToggleColumn::make('is_active')->label('On'),
                TextColumn::make('note')->label('Note')->toggleable(isToggledHiddenByDefault: true)->wrap(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Made')->options(['auto' => 'By itself', 'manual' => 'By hand']),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the redirect'),
                DeleteAction::make()->label('Delete'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
