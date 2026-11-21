<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Support\Translated;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

/** Categories: one list in tree order, like the ECT list. The code (C-12) shows beside every name. */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'working_name';

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('categories') ?? false;
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return static::allowed(); }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return static::allowed(); }
    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool { return static::allowed(); }
    public static function canReplicate(\Illuminate\Database\Eloquent\Model $record): bool { return static::allowed(); }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('code_shown')
                    ->label('Code')
                    ->helperText('Made by the system. It never changes.')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (TextInput $component, ?Category $record) => $component->state($record?->code))
                    ->visible(fn (?Category $record) => $record !== null),
                ...Translated::text('name', 'Category name', required: true),
                TextInput::make('working_name')
                    ->label('Working name')
                    ->helperText('Only for you. Use it to tell two similar names apart. Visitors never see it.')
                    ->maxLength(255),
                Select::make('parent_id')
                    ->label('Sits inside')
                    ->placeholder('Top level (a main menu heading)')
                    ->options(fn (?Category $record) => Category::choices($record?->id))
                    ->rules(fn (?Category $record) => [function (string $attribute, $value, \Closure $fail) use ($record) {
                        // An article sits only in a last-level category. A category that holds articles cannot get sub-categories until they are moved.
                        if ($value && (int) $value !== (int) $record?->parent_id && ($parent = Category::find($value)) && $parent->holdsArticles()) {
                            $fail('"'.$parent->mainName().'" already holds articles, so it cannot get sub-categories yet. Move its articles into an Overview category first.');
                        }
                    }])
                    ->searchable(),
                TextInput::make('slug')
                    ->label('Web address (short name)')
                    ->helperText('Letters, numbers and dashes. Left empty, it is made from the name.')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                        if (in_array(strtolower((string) $value), Category::RESERVED_SLUGS, true)) {
                            $fail('This word is already used by the site. Please choose another.');
                        }
                    }]),
                \App\Filament\Support\ImageUpload::make('image', 'categories')
                    ->label('Picture')
                    ->imageEditor(),
                ...Translated::text('image_alt', 'Describe the picture (for people who cannot see it)'),
                ...Translated::textarea('description', 'Description', rows: 4),
                ...Translated::textarea('header_text', 'Header text', rows: 3, help: 'Shown above the list on the category page.'),
                Section::make('Big menu panel (optional)')
                    ->collapsed()
                    ->description('In the main menu a heading with sub-categories opens a wide panel: the sub-categories on the left (each with its icon and the start of its description) and one feature card on the right. The icon shows when this category is listed in a panel. The card boxes are used when this is a main menu heading; leave them empty and the card is made from this category (its picture, name and description).')
                    ->schema([
                        Select::make('custom_data.menu_style')->label('Shape of the panel (for a main menu heading)')->options(['wide' => 'Wide: across the whole menu bar, with a quiet card', 'compact' => 'Compact: a small panel under the heading, with a bold coloured card'])->default('wide')->native(true),
                        Select::make('menu_icon')->label('Icon in the panel')->options(\App\Support\MenuIcons::options())->placeholder('No icon')->native(false),
                        ...Translated::text('feature_title', 'Card: title', max: 120),
                        ...Translated::textarea('feature_text', 'Card: short text', rows: 2),
                        ...Translated::text('feature_button', 'Card: button words', max: 40, help: 'For example: Start here. Empty: Explore.'),
                        Toggle::make('custom_data.menu_challenge_card')->label('Also show a "Challenge yourself" card in this panel')->helperText('A second card with the tests that are switched on. It shows only while at least one test is on.')->default(false),
                        \App\Filament\Support\ImageUpload::make('custom_data.menu_challenge_image', 'menu')->label('Challenge card: picture')->helperText('The picture on the "Challenge yourself" card. Without one, a small question-mark icon is shown.'),
                        \App\Filament\Support\ImageUpload::make('feature_image', 'menu')->label('Card: picture')->helperText('Empty: the picture of this category.'),
                        TextInput::make('feature_url')->label('Card: where the button goes')->maxLength(500)->placeholder('/category/the-quran or https://...')
                            ->helperText('An address of this site (starts with /) or a full web address (https://). Empty: this category.')
                            ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                                if (filled($value) && \App\Support\SiteMenu::safeUrl((string) $value) === null) {
                                    $fail('Start with / for a page of this site, or with https:// for another website.');
                                }
                            }]),
                    ])->columns(1),
                TextInput::make('sort_order')->label('Order')->numeric()->minValue(0)->default(0),
                Toggle::make('is_visible')->label('Show on the site')->default(true),
                Toggle::make('custom_data.hide_articles')->label('Do not list its articles on its page')
                    ->helperText('For a page that shows something else, for example the Did You Know? facts. The articles stay in the admin and in search; only the list on this page is left out.')
                    ->default(false),
                Toggle::make('custom_data.topic_tabs')->label('Show its sub-categories as tabs')
                    ->helperText('For a section that has many items, like Stories in pictures. Its page shows the tabs All and one for each sub-category, and each tab lists only its own items, a page at a time.')
                    ->default(false),
                Section::make('Search engines (optional)')
                    ->collapsed()
                    ->description('Leave empty and the name and description are used.')
                    ->schema([
                        ...Translated::text('page_title', 'Page title'),
                        ...Translated::textarea('meta_description', 'Meta description', rows: 2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        // One list of all categories for the rows of this one drawing of the table (not kept between requests).
        $everyCategory = new \ArrayObject;

        return $table
            ->defaultSort('tree_path')
            ->columns([
                TextInputColumn::make('sort_order')->label('Order')->type('number')->rules(['integer', 'min:0'])->width('4.5rem')
                    ->extraInputAttributes(['class' => 'if-order', 'style' => 'width:3.8rem;min-width:0;text-align:center;padding-inline:.25rem']),
                TextColumn::make('tree_path')
                    ->label('Category path')
                    ->state(fn (Category $record) => static::parentPath($record, $everyCategory))
                    ->formatStateUsing(fn ($state) => \App\Filament\Support\Cells::scroll($state, 'if-scroll--path'))
                    ->html()
                    ->color('gray')
                    ->size('xs'),
                TextColumn::make('name')
                    ->label('Category name')
                    ->state(fn (Category $record) => str_repeat('— ', $record->depth).$record->mainName().' ('.$record->code.')')
                    ->formatStateUsing(fn ($state) => \App\Filament\Support\Cells::scroll($state, 'if-scroll--name'))
                    ->html()
                    ->description(fn (Category $record) => $record->working_name)
                    ->weight(fn (Category $record) => $record->depth === 0 ? 'bold' : null)
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where(fn ($q) => \App\Support\TextSearch::match($q, ['name', 'working_name'], $search)
                            ->orWhere('id', ltrim(strtoupper($search), 'C-') ?: 0))),
                TextColumn::make('articles_total')->label('Articles')->state(fn (Category $record) => $record->articleCount() ?: '-')->alignCenter(),
                IconColumn::make('is_visible')->label('Shown')->boolean(),
            ])
            ->filters([
                SelectFilter::make('parent_id')->label('In category')->options(fn () => Category::choices())->searchable(),
                TernaryFilter::make('is_visible')->label('Shown on the site'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->label('Modify'),
                ReplicateAction::make()
                    ->label('Clone')
                    ->excludeAttributes(['slug', 'tree_path', 'depth'])
                    ->beforeReplicaSaved(function (Category $replica, Category $record) {
                        $names = $record->getTranslations('name');
                        $replica->setTranslations('name', array_map(fn ($n) => $n.' (copy)', $names));
                        $replica->slug = Str::slug($record->mainName()).'-copy-'.Str::lower(Str::random(4));
                        $replica->is_visible = false;
                    }),
                static::deleteAction(),
            ]);
    }

    /** Delete: a category that still holds sub-categories or articles must be emptied first, so nothing is lost by accident. */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Delete')
            ->modalDescription('The category goes to the Trash. You can bring it back from there.')
            ->before(function (DeleteAction $action, Category $record) {
                if ($record->children()->exists() || $record->articleCount() > 0) {
                    Notification::make()->danger()->title('This category is not empty')
                        ->body('It still has sub-categories or articles. Move or delete them first, so nothing is lost.')->persistent()->send();
                    $action->cancel();
                }
            });
    }

    /** The names above a category, small and grey, as in the ECT list. */
    private static function parentPath(Category $record, \ArrayObject $cache): ?string
    {
        if (! $record->parent_id) {
            return null;
        }
        $cache['all'] ??= Category::withTrashed()->get()->keyBy('id');
        $parent = $cache['all']->get($record->parent_id);

        return $parent ? Category::pathFor($parent, $cache['all']) : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
