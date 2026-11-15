<?php

namespace App\Filament\Resources\SearchSynonyms;

use App\Filament\Resources\SearchSynonyms\Pages\ManageSearchSynonyms;
use App\Models\SearchSynonym;
use App\Support\Synonyms;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Search synonyms: words the site search treats as the same, so a visitor who types one also finds the others
 * (Quran, Qur'an, Koran). A moderator or the super user.
 */
class SearchSynonymResource extends Resource
{
    protected static ?string $model = SearchSynonym::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Search synonyms';

    protected static ?string $modelLabel = 'group of words';

    protected static ?string $pluralModelLabel = 'groups of words';

    protected static ?string $slug = 'search-synonyms';

    protected static ?int $navigationSort = 4;

    private static function allowed(): bool
    {
        return auth()->user()?->canPublish() ?? false;
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }
    public static function canEdit(Model $record): bool { return static::allowed(); }
    public static function canDelete(Model $record): bool { return static::allowed(); }
    public static function canDeleteAny(): bool { return static::allowed(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TagsInput::make('terms')->label('Words that mean the same')->required()->placeholder('Type a word and press Enter')
                ->helperText('At least two. A word can be several words, for example "day of judgment". Capital letters do not matter. If a word is in two groups that are on, the two groups are joined. A word added from a group must have at least '.Synonyms::MIN_LETTERS.' letters, so a very short spelling does not match half the words of the site.')
                ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                    $terms = Synonyms::cleanTerms((array) $value);
                    if (count($terms) < 2) {
                        $fail('Write at least two different words.');
                    } elseif (count($terms) > 30) {
                        $fail('A group can have at most 30 words.');
                    }
                }]),
            TextInput::make('note')->label('Note (only for you)')->maxLength(255),
            Toggle::make('is_active')->label('On')->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('terms')->label('Words that mean the same')->badge()->separator(',')->wrap()
                    ->state(fn (SearchSynonym $r) => implode(',', (array) $r->terms))->searchable(query: fn ($query, string $search) => $query->where('terms', 'like', '%'.mb_strtolower($search).'%')),
                TextColumn::make('note')->label('Note')->wrap()->limit(70)->toggleable(),
                ToggleColumn::make('is_active')->label('On'),
            ])
            ->filters([TernaryFilter::make('is_active')->label('On')])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the group'),
                DeleteAction::make()->label('Delete'),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSearchSynonyms::route('/')];
    }
}
