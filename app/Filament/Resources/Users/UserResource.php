<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * People: who can log in, with which role, and which admin screens a staff person may open.
 * Only the super user sees this. There is no Delete: a person is switched off instead, so their articles and comments stay.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?string $modelLabel = 'person';

    protected static ?string $pluralModelLabel = 'people';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool { return (bool) auth()->user()?->isSuperuser(); }
    public static function canCreate(): bool { return (bool) auth()->user()?->isSuperuser(); }
    public static function canEdit(Model $record): bool { return (bool) auth()->user()?->isSuperuser(); }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('The person')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()->revealable()
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->minLength(10)
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the present password.' : 'At least 10 characters.'),
                        Select::make('role')->options(User::ROLES)->required()->live()
                            ->helperText('Super user: everything. Moderator: approves, publishes and replies. Author: writes their own articles. Member: uses the public site only.'),
                        Select::make('status')->options(User::STATUSES)->default('active')->required()
                            ->helperText('Switched off: the person cannot log in. Their articles and comments stay.'),
                    ])->columns(2),
                Section::make('Admin screens this person may open')
                    ->description('Only for a Moderator or an Author. Leave all empty for the usual screens of the role (Moderator: Articles, Categories, Comments. Author: Articles). Ticks replace the usual screens. People and Site settings are only for the super user.')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('role'), ['moderator', 'author'], true))
                    ->schema([
                        CheckboxList::make('admin_screens')->hiddenLabel()->options(User::SCREENS)->columns(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge()->formatStateUsing(fn ($state) => User::ROLES[$state] ?? $state)
                    ->color(fn ($state) => ['superuser' => 'danger', 'moderator' => 'warning', 'author' => 'info', 'member' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => User::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['active' => 'success', 'waiting' => 'warning', 'switched_off' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('screens')->label('Screens')
                    ->state(fn (User $record) => match (true) {
                        $record->role === 'superuser' => 'All',
                        ! in_array($record->role, ['moderator', 'author'], true) => '-',
                        empty($record->admin_screens) => 'Usual for the role',
                        default => collect($record->admin_screens)->map(fn ($s) => User::SCREENS[$s] ?? $s)->implode(', '),
                    })->color('gray'),
                TextColumn::make('created_at')->label('Joined')->date()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(User::ROLES),
                SelectFilter::make('status')->options(User::STATUSES),
            ])
            ->recordActions([
                EditAction::make()->label('Modify'),
                Action::make('toggle')
                    ->label(fn (User $record) => $record->status === 'active' ? 'Switch off' : 'Switch on')
                    ->color(fn (User $record) => $record->status === 'active' ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->id !== auth()->id())
                    ->action(function (User $record) {
                        $record->update(['status' => $record->status === 'active' ? 'switched_off' : 'active']);
                        Notification::make()->success()->title($record->status === 'active' ? 'Switched on.' : 'Switched off.')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
