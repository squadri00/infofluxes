<?php

namespace App\Filament\Resources\People;

use App\Filament\Resources\People\Pages\ManagePeople;
use App\Models\Person;
use App\Support\Html;
use App\Support\Social;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/** Contributors: the people credited on articles. The ones with "public page" on appear on the Contributors page of the site. */
class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Contributors';

    protected static ?string $modelLabel = 'contributor';

    protected static ?int $navigationSort = 7;

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('people') ?? false;
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
            TextInput::make('name')->label('Name')->required()->maxLength(255),
            TextInput::make('slug')->label('Web address (short name)')->helperText('Left empty, it is made from the name. The page opens at /people/this-name.')->maxLength(120)
                ->rules([fn (?Model $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                    if (filled($value) && Person::withTrashed()->where('slug', Str::slug((string) $value))->when($record, fn ($q) => $q->where('id', '!=', $record->id))->exists()) {
                        $fail('Another contributor already has this address.');
                    }
                }]),
            TextInput::make('role_title')->label('Role (shown next to their name on articles)')->helperText('For example Research contributor.')->maxLength(255),
            TextInput::make('tradition')->label('Tradition or background')->maxLength(255),
            Textarea::make('bio')->label('Short bio')->helperText('Two or three lines.')->rows(3)->maxLength(2000),
            Textarea::make('qualification_note')->label('What qualifies them')->rows(2)->maxLength(2000),
            \App\Filament\Support\ImageUpload::make('photo', 'people')->label('Photo'),
            TextInput::make('photo_alt')->label('Describe the photo (for people who cannot see it)')->maxLength(255),
            TextInput::make('link')->label('Their own website (optional)')->maxLength(2048)
                ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                    if (filled($value) && Social::safeUrl($value) === null) {
                        $fail('Use a full web address that starts with https://.');
                    }
                }]),
            Toggle::make('show_page')->label('Give them a public page and list them on the Contributors page')->default(false),
            RichEditor::make('page_body')->label('Text of their page (optional)')->columnSpanFull(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => '[K-'.$state.']')->sortable(),
                ImageColumn::make('photo')->disk('public')->circular()->size(34),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('role_title')->label('Role')->searchable(),
                TextColumn::make('tradition')->label('Tradition')->toggleable(),
                IconColumn::make('show_page')->label('Public page')->boolean(),
                TextColumn::make('articles_count')->label('Articles')->counts('articles')->sortable(),
                TextColumn::make('slug')->label('Address')->formatStateUsing(fn ($state) => '/people/'.$state)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the contributor')->modalWidth(Width::FiveExtraLarge)
                    ->mutateDataUsing(function (array $data, Person $record) {
                        $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : $record->slug;
                        $data['page_body'] = Html::clean((string) ($data['page_body'] ?? ''));

                        return $data;
                    }),
                DeleteAction::make()->label('Delete')->modalDescription('The articles stay; they just lose this credit.'),
            ])
            ->paginated([25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePeople::route('/')];
    }
}
