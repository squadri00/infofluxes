<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Filament\Support\Translated;
use App\Models\Page;
use App\Support\Html;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** The website pages (About, Contact, Privacy...). Each opens at /its-address, and the ticked ones are listed in the footer. */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'page';

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('pages') ?? false;
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
        $main = config('infofluxes.default_locale');

        return $schema->components([
            ...Translated::text('title', 'Title', required: true),
            TextInput::make('slug')->label('Web address (short name)')->helperText('Left empty, it is made from the title. The page opens at /this-name.')->maxLength(120)
                ->rules([fn (?Model $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                    if (filled($value) && Page::slugTaken(\Illuminate\Support\Str::slug((string) $value), $record?->id)) {
                        $fail('This address is already used by the site, an article or another page. Please choose another.');
                    }
                }]),
            ...Translated::rich('body', 'The page', help: 'Headings, lists, bold and links are allowed. Names are written with their salutation after them.'),
            ...Translated::textarea('meta_description', 'Short description for search engines (optional)', rows: 2),
            Select::make('status')->label('Status')->options(Page::STATUSES)->default('draft')->required()->native(true),
            Toggle::make('in_footer')->label('List this page in the footer')->default(true),
            TextInput::make('sort_order')->label('Order in the footer (small numbers first)')->numeric()->default(0),
            Toggle::make('no_index')->label('Ask search engines not to list it')->default(false),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => '['.'P-'.$state.']')->sortable(),
                TextColumn::make('title')->state(fn (Page $r) => $r->getTranslation('title', config('infofluxes.default_locale'), true))->searchable(['title']),
                TextColumn::make('slug')->label('Web address')->formatStateUsing(fn ($state) => '/'.$state),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Page::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                IconColumn::make('in_footer')->label('Footer')->boolean(),
                TextColumn::make('sort_order')->label('Order')->sortable(),
                TextColumn::make('updated_at')->label('Changed')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(Page::STATUSES)])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the page')->modalWidth(Width::SevenExtraLarge)
                    ->mutateDataUsing(function (array $data, Page $record) {
                        // An empty address keeps the old one.
                        $data['slug'] = filled($data['slug'] ?? null) ? \Illuminate\Support\Str::slug($data['slug']) : $record->slug;

                        return static::cleanBody($data);
                    }),
                ReplicateAction::make()->label('Clone')
                    ->excludeAttributes(['slug'])
                    ->beforeReplicaSaved(function (Page $replica, Page $record) {
                        $replica->slug = Page::uniqueSlug($record->getTranslation('title', config('infofluxes.default_locale'), true).' copy');
                        $replica->status = 'draft';
                    }),
                DeleteAction::make()->label('Delete'),
            ])
            ->paginated([25, 50]);
    }

    /** The words of a page are cleaned the same way an article's are: no scripts, no unsafe links. */
    public static function cleanBody(array $data): array
    {
        foreach ((array) ($data['body'] ?? []) as $locale => $html) {
            $data['body'][$locale] = Html::clean((string) $html);
        }

        return $data;
    }

    public static function getPages(): array
    {
        return ['index' => ManagePages::route('/')];
    }
}
