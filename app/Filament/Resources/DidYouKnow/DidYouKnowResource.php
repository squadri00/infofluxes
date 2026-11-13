<?php

namespace App\Filament\Resources\DidYouKnow;

use App\Filament\Resources\DidYouKnow\Pages\ManageDidYouKnow;
use App\Filament\Support\Translated;
use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Support\Html;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Did You Know?: short facts listed like a FAQ. Drag the rows to change the order. A fact needs a source before it can be published. */
class DidYouKnowResource extends Resource
{
    protected static ?string $model = DidYouKnow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Did You Know?';

    protected static ?string $modelLabel = 'fact';

    protected static ?int $navigationSort = 3;

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('facts') ?? false;
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
            ...Translated::text('title', 'Heading (a question or a short statement)', required: true),
            ...Translated::rich('body', 'The short answer', required: true),
            TextInput::make('source')->label('Source: where to check it')->maxLength(500)
                ->helperText('A web address you opened and read, or the name of the book with chapter and page, for example: Sahih al-Bukhari, Book of Faith, hadith 8. Needed before the fact can be published.')
                ->required(fn (Get $get) => $get('status') === 'published')
                ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                    if (filled($value) && ! static::goodSource((string) $value)) {
                        $fail('Name the book (with chapter and page) or give a web address you opened. "AI" or a tool name is not a source.');
                    }
                }]),
            Select::make('article_id')->label('Link to the full article (optional)')->searchable()
                ->getSearchResultsUsing(fn (string $search) => Article::query()->published()->where('title->'.config('infofluxes.default_locale'), 'like', '%'.$search.'%')->limit(30)->get()->mapWithKeys(fn (Article $a) => [$a->id => $a->mainTitle().' ('.$a->code.')'])->all())
                ->getOptionLabelUsing(fn ($value) => ($a = Article::find($value)) ? $a->mainTitle().' ('.$a->code.')' : null)
                ->helperText('A long list or a long story belongs in an article. The card then shows the short answer and a "Read the full article" button.'),
            Select::make('category_id')->label('Show it on the page of this category (optional)')->options(fn () => Category::choices())->searchable()->placeholder('Only on the Did You Know? page'),
            Select::make('status')->label('Status')->options(DidYouKnow::STATUSES)->default('draft')->required()->live()->native(true),
            TextInput::make('reviewer_name')->label('Reviewed by')->maxLength(255)
                ->helperText('The person who checked it. Written with AI help? Leave "Written with AI help" on until a person has read and checked it.')
                ->required(fn (Get $get) => $get('status') === 'published' && (bool) $get('ai_assisted')),
            Toggle::make('ai_assisted')->label('Written with AI help')->live()->default(false),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => '[D-'.$state.']')->sortable(),
                TextColumn::make('title')->label('Did you know?')->state(fn (DidYouKnow $r) => $r->getTranslation('title', config('infofluxes.default_locale'), true))->searchable(['title'])->limit(90)->wrap(),
                TextColumn::make('category.name')->label('Category')->state(fn (DidYouKnow $r) => $r->category?->mainName())->placeholder('—')->toggleable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => DidYouKnow::STATUSES[$state] ?? $state)->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                IconColumn::make('source')->label('Source')->boolean()->state(fn (DidYouKnow $r) => filled($r->source)),
                TextColumn::make('reviewer_name')->label('Reviewed by')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(DidYouKnow::STATUSES),
                SelectFilter::make('category_id')->label('Category')->options(fn () => Category::choices()),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the fact')->modalWidth(Width::FourExtraLarge)
                    ->mutateDataUsing(fn (array $data) => static::clean($data)),
                ReplicateAction::make()->label('Clone')->excludeAttributes(['sort_order'])
                    ->beforeReplicaSaved(function (DidYouKnow $replica) {
                        $replica->status = 'draft';
                        $replica->sort_order = (int) DidYouKnow::max('sort_order') + 1;
                    }),
                DeleteAction::make()->label('Delete'),
            ])
            ->paginated(false);
    }

    /** The answer is cleaned the same way an article is (no scripts, no unsafe links), and pasted leftovers are removed. */
    public static function clean(array $data): array
    {
        foreach ((array) ($data['body'] ?? []) as $locale => $html) {
            $data['body'][$locale] = static::tidy(Html::clean((string) $html));
        }

        return $data;
    }

    /**
     * Leftovers of a paste from a chat tool: the little source chip (a line like "Province of Manitoba+1") and empty paragraphs.
     * Only a paragraph that is nothing but such a chip is removed; the words of the answer are never touched.
     */
    public static function tidy(string $html): string
    {
        $html = preg_replace('~<p>\s*[^<]{2,80}\+\d{1,2}\s*</p>~u', '', $html) ?? $html;

        return preg_replace('~<p>(\s|&nbsp;|<br\s*/?>)*</p>~i', '', $html) ?? $html;
    }

    /** Is this a source a reader could check? Not a tool name, not a few letters. */
    public static function goodSource(string $source): bool
    {
        $source = trim($source);
        $plain = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $source));
        if (preg_match('#^https?://\S+\.\S+#i', $source)) {
            return true;
        }

        return mb_strlen(trim($plain)) >= 8 && ! in_array(trim($plain), ['ai', 'chatgpt', 'chat gpt', 'gpt', 'gemini', 'claude', 'copilot', 'internet', 'google', 'online', 'the internet', 'various', 'unknown'], true);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDidYouKnow::route('/')];
    }
}
