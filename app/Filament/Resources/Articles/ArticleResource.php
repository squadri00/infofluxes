<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Support\Translated;
use App\Models\Article;
use App\Models\Category;
use App\Support\YouTube;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

/** Articles: the list (like the ECT product list) and one edit page with everything on it. */
class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'working_name';

    private static function screen(): bool
    {
        return auth()->user()?->canUseScreen('articles') ?? false;
    }

    /** A moderator or the super user works on every article; an author only on their own. */
    private static function mine(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return static::screen() && ($user->canPublish() || (int) $record->author_id === $user->id);
    }

    public static function canViewAny(): bool { return static::screen(); }
    public static function canCreate(): bool { return static::screen(); }
    public static function canReplicate(\Illuminate\Database\Eloquent\Model $record): bool { return static::mine($record); }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return static::mine($record); }
    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool { return static::mine($record); }
    public static function canDeleteAny(): bool { return static::screen(); }

    /** An author may delete only their own articles that are not published yet. */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::mine($record) && (auth()->user()->canPublish() || in_array($record->status, ['draft', 'written', 'in_review'], true));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user && ! $user->canPublish() ? $query->where('author_id', $user->id) : $query;
    }

    /** The statuses this person may choose. An author can only keep a draft or send it for review. */
    public static function statusOptions(): array
    {
        $role = auth()->user()?->role;

        return in_array($role, ['superuser', 'moderator'], true)
            ? Article::STATUSES
            : array_intersect_key(Article::STATUSES, array_flip(['draft', 'written', 'in_review']));
    }

    /** The categories whose path or code contains the typed words (at most 50). Done in memory, with no database search. */
    public static function searchCategories(string $search): array
    {
        $words = array_filter(preg_split('/\s+/u', mb_strtolower(trim($search))));
        $found = [];
        foreach (Category::leafChoices() as $id => $label) {
            $text = mb_strtolower($label);
            if (collect($words)->every(fn ($w) => str_contains($text, $w))) {
                $found[$id] = $label;
                if (count($found) >= 50) {
                    break;
                }
            }
        }

        return $found;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('The article')
                    ->collapsible()
                    ->collapsed(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        TextInput::make('code_shown')
                            ->label('Code')
                            ->helperText('Made by the system. It never changes.')
                            ->disabled()->dehydrated(false)
                            ->afterStateHydrated(fn (TextInput $component, ?Article $record) => $component->state($record?->code))
                            ->visible(fn (?Article $record) => $record !== null),
                        ...Translated::text('title', 'Title', required: true),
                        TextInput::make('working_name')->label('Working name')->helperText('Only for you. Visitors never see it.')->maxLength(255),
                        Select::make('category_id')
                            ->label('Main category')
                            ->options(fn () => Category::leafChoices())
                            ->helperText('The code of each category shows in the list, for example (C-12). Only the last categories of the tree are offered. An article sits in a category that has no sub-categories; use its "Overview" category for an overview article.')
                            ->searchable()
                            ->required(),
                        Select::make('extraCategories')
                            ->label('Also in these categories (optional)')
                            ->relationship('extraCategories', 'id')   // this saves the choice; the search below does not ask the database
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::searchCategories($search))
                            ->getOptionLabelsUsing(fn (array $values): array => array_intersect_key(Category::choices(), array_flip($values)))
                            ->helperText('Type part of a name, or a code such as C-12, to find a category.'),
                        ...Translated::textarea('short_detail', 'Short detail', rows: 3, help: 'One or two sentences. Shown in lists and when the article is shared.'),
                        ...Translated::rich('long_detail', 'Long detail'),
                        TagsInput::make('search_words')->label('Search words')->helperText('Type a word or phrase and press Enter. Add as many as you like.')->columnSpanFull(),
                        TextInput::make('source_line')->label('Source (optional)')->helperText('One line. It shows on the page only when it is filled in.')->maxLength(500),
                    ])->columns(1),

                Section::make('Pictures')
                    ->collapsed(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        FileUpload::make('main_image')->label('Main picture')->image()
                            ->acceptedFileTypes(\App\Support\StoryImage::TYPES)->disk(\App\Support\StoryImage::DISK)->directory(\App\Support\StoryImage::DIRECTORY)
                            ->imageEditor()->imageCropAspectRatio(fn () => \App\Support\StoryImage::ratioText())->imageEditorAspectRatioOptions(fn () => [\App\Support\StoryImage::ratioText()])
                            ->maxSize(fn () => \App\Support\ImageStore::maxKb())
                            ->saveUploadedFileUsing(fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string => \App\Support\StoryImage::store($file))
                            ->helperText('It is cut to the shape set for the site (16:9) and saved as a small WebP.'),
                        ...Translated::text('main_image_alt', 'Describe the main picture (for people who cannot see it)'),
                        TextInput::make('main_image_credit')->label('Picture credit')->maxLength(255),
                        TextInput::make('main_image_licence')->label('Picture licence (optional)')->helperText('For example: Public domain, CC BY-SA 4.0.')->maxLength(255),
                        Repeater::make('images')
                            ->label('More pictures')
                            ->relationship('images')
                            ->orderColumn('sort_order')
                            ->addActionLabel('Add a picture')
                            ->collapsible()->collapsed()
                            ->itemLabel(fn (array $state) => $state['alt']['en'] ?? 'Picture')
                            ->defaultItems(0)
                            ->schema([
                                \App\Filament\Support\ImageUpload::make('image', 'articles')->label('Picture')->required(),
                                ...Translated::text('alt', 'Describe the picture'),
                                ...Translated::text('caption', 'Caption (optional)'),
                            ]),
                    ])->collapsible(),

                Section::make('Audio for the Listen button')
                    ->description('Optional. Without a file, the Listen button reads the words aloud with the voice of the visitor\'s own device (free). A file plays instead, but only once it is Approved.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        FileUpload::make('audio')->label('One audio file for the whole article (optional)')->disk('public')->directory('audio/articles')->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])->maxSize(30720)->helperText('An MP3 file, up to 30 MB.'),
                        Select::make('audio_state')->label('Audio state')->options(['draft' => 'Draft: not played yet', 'approved' => 'Approved: it is played'])->default('draft')->dehydrateStateUsing(fn ($state) => $state ?: 'draft')
                            ->helperText('Listen plays the file only when it is Approved. A new file starts as Draft. Only a moderator or the super user can approve.')
                            ->disabled(fn () => ! auth()->user()?->canPublish())->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                    ])->columns(2),

                Section::make('YouTube videos')
                    ->collapsed(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        Repeater::make('videos')
                            ->hiddenLabel()
                            ->relationship('videos')
                            ->orderColumn('sort_order')
                            ->addActionLabel('Add a video')
                            ->itemLabel(fn (array $state) => $state['url'] ?? 'Video')
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('url')->label('YouTube address')->url()->required()->maxLength(500)
                                    ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                                        if (YouTube::id($value) === null) {
                                            $fail('This is not a YouTube video address. Paste the address of one video.');
                                        }
                                    }]),
                                ...Translated::text('title', 'Video title (optional)'),
                            ]),
                    ])->collapsible(),

                Section::make(fn () => __('review.section'))
                    ->description(fn () => __('review.section_help'))
                    ->visible(fn (?\App\Models\Article $record) => $record && ($record->ai_assisted || filled($record->custom_data['please_check'] ?? null)))
                    ->collapsible()
                    ->schema([
                        \Filament\Forms\Components\CheckboxList::make('custom_data.review.done')->label(fn () => __('review.standard_label'))
                            ->options(fn () => \App\Support\ReviewChecklist::standard())->columns(1),
                        \Filament\Forms\Components\CheckboxList::make('custom_data.review.points_done')->label(fn () => __('review.points_label'))
                            ->options(fn (?\App\Models\Article $record) => \App\Support\ReviewChecklist::points($record?->custom_data))->columns(1)
                            ->visible(fn (?\App\Models\Article $record) => \App\Support\ReviewChecklist::points($record?->custom_data) !== []),
                    ]),

                Section::make('Publishing')
                    ->collapsible()
                    ->collapsed(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        Select::make('status')->options(fn () => static::statusOptions())->default('draft')->required(),
                        DateTimePicker::make('publish_date')->label('Publish date')->helperText('Left empty, it is the moment you publish. A date in the future keeps the article hidden until then.'),
                        Select::make('author_id')->label('Author')->relationship('author', 'name')->default(fn () => auth()->id())->searchable()->preload()->disabled(fn () => ! auth()->user()?->canPublish())->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        Select::make('contributor_id')->label('Contributor (credited on the article)')->relationship('contributor', 'name')->searchable()->preload()
                            ->helperText('A person from Content > Contributors. Their name shows under the title and links to their page when they have one.')
                            ->disabled(fn () => ! auth()->user()?->canPublish())->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        \Filament\Forms\Components\Toggle::make('is_featured')->label('Featured on the home page')->live()
                            ->helperText('A featured article shows in the Featured part of the home page (when it is published).')
                            ->disabled(fn () => ! auth()->user()?->canPublish())->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        TextInput::make('featured_order')->label('Featured order')->numeric()->minValue(0)->default(0)
                            ->helperText('1 comes first, then 2, and so on. 0 means after the numbered ones.')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('is_featured'))
                            ->disabled(fn () => ! auth()->user()?->canPublish())->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        TextInput::make('reviewer_name')->label('Reviewer name')->helperText('The person who checked it. Needed before an article written with AI help can be published.')->maxLength(255),
                        \Filament\Forms\Components\Toggle::make('custom_data.accordion')->label('Show the parts as drop-down sections')->helperText('Each main heading becomes a drop-down section (it needs at least two main headings).'),
                        \Filament\Forms\Components\Toggle::make('ai_assisted')->label('Written with AI help')->helperText('Made by the AI import. Leave it on until a person has read and checked the article.')->disabled(fn () => ! auth()->user()?->canPublish())->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        TextInput::make('slug')->label('Web address (short name)')->helperText('Left empty, it is made from the title.')->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                                if (in_array(strtolower((string) $value), Category::RESERVED_SLUGS, true)) {
                                    $fail('This word is already used by the site. Please choose another.');
                                }
                            }]),
                        TextInput::make('sort_order')->label('Order')->numeric()->minValue(0)->default(0),
                    ])->columns(2),

                Section::make('Search engines (optional)')
                    ->collapsed()
                    ->description('Leave empty and the title and short detail are used.')
                    ->schema([
                        ...Translated::text('page_title', 'Page title'),
                        ...Translated::textarea('meta_description', 'Meta description', rows: 2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'extraCategories', 'author']))
            ->columns([
                TextColumn::make('code')->label('ID')->state(fn (Article $record) => $record->code)->sortable(query: fn (Builder $q, string $dir) => $q->orderBy('id', $dir))->weight('bold'),
                TextColumn::make('reactions')->label('Likes / dislikes')->state(fn (Article $record) => $record->likesCount().' / '.$record->dislikesCount())->toggleable(),
                TextColumn::make('title')->label('Title')
                    ->state(fn (Article $record) => $record->mainTitle())
                    ->formatStateUsing(fn ($state) => \App\Filament\Support\Cells::scroll($state, 'if-scroll--title'))
                    ->html()
                    ->description(fn (Article $record) => trim((($record->ai_assisted ? '✨ AI · ' : '').$record->working_name)))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($q) => $q
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('working_name', 'like', "%{$search}%")
                        ->orWhere('search_words', 'like', "%{$search}%")
                        ->orWhere('id', ltrim(strtoupper($search), 'A-') ?: 0))),
                TextColumn::make('categories_shown')->label('Categories')
                    ->state(fn (Article $record) => $record->allCategories()->map(fn ($c) => $c->mainName().' ('.$c->code.')')->implode('  ·  '))
                    ->formatStateUsing(fn ($state) => \App\Filament\Support\Cells::scroll($state, 'if-scroll--cats'))
                    ->html()
                    ->color('gray')
                    ->size('xs'),
                \Filament\Tables\Columns\ToggleColumn::make('is_featured')->label('Featured')->onIcon('heroicon-s-star')->offIcon('heroicon-o-star')->onColor('warning')
                    ->disabled(fn () => ! auth()->user()?->canPublish()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Article::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['published' => 'success', 'in_review' => 'warning', 'written' => 'info', 'draft' => 'gray', 'archived' => 'danger'][$state] ?? 'gray'),
                \Filament\Tables\Columns\IconColumn::make('ai_assisted')->label('AI')->boolean()->trueIcon('heroicon-o-sparkles')->falseIcon('')->trueColor('warning')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('author.name')->label('Author')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('publish_date')->label('Publish date')->date('j M Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Article::STATUSES),
                \Filament\Tables\Filters\TernaryFilter::make('is_featured')->label('Featured on the home page'),
                SelectFilter::make('category')->label('In category (and everything below it)')->options(fn () => Category::choices())->searchable()
                    // A heading shows what sits in it and in every sub-category below it, because articles sit only in the last-level categories.
                    ->query(function (Builder $query, array $data) {
                        if (! filled($data['value'] ?? null) || ! ($category = Category::find($data['value']))) {
                            return $query;
                        }
                        $ids = $category->descendantIds();

                        return $query->where(fn ($q) => $q->whereIn('category_id', $ids)->orWhereHas('extraCategories', fn ($c) => $c->whereIn('categories.id', $ids)));
                    }),
                SelectFilter::make('author_id')->label('Author')->relationship('author', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->label('Modify'),
                \Filament\Actions\Action::make('write_with_ai')->label('Write with AI help')->icon('heroicon-o-sparkles')->color('gray')
                    ->visible(fn (Article $record) => \App\Support\IndexEntries::isBlank($record) && (auth()->user()?->canUseScreen('import') ?? false))
                    ->url(fn (Article $record) => \App\Filament\Pages\AiImport::getUrl(['entry' => $record->id])),
                ReplicateAction::make()
                    ->label('Clone')
                    ->excludeAttributes(['slug', 'is_featured', 'featured_order'])
                    ->beforeReplicaSaved(function (Article $replica, Article $record) {
                        $replica->setTranslations('title', array_map(fn ($t) => $t.' (copy)', $record->getTranslations('title')));
                        $replica->slug = Str::slug($record->mainTitle()).'-copy-'.Str::lower(Str::random(4));
                        $replica->status = 'draft';
                        $replica->author_id = auth()->id();
                    })
                    ->after(function (Article $replica, Article $record) {
                        // The copy also sits in the same other categories and has the same pictures and videos.
                        $replica->extraCategories()->sync($record->extraCategories->pluck('id')->all());
                        foreach ($record->images as $image) {
                            $replica->images()->create($image->only(['image', 'sort_order']) + ['alt' => $image->getTranslations('alt'), 'caption' => $image->getTranslations('caption')]);
                        }
                        foreach ($record->videos as $video) {
                            $replica->videos()->create(['url' => $video->url, 'sort_order' => $video->sort_order, 'title' => $video->getTranslations('title')]);
                        }
                    }),
                DeleteAction::make()->label('Delete')->modalDescription('The article goes to the Trash. You can bring it back from there.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign')
                        ->visible(fn () => (bool) auth()->user()?->canPublish())
                        ->label('Assign to a category')
                        ->icon('heroicon-o-folder-plus')
                        ->schema([Select::make('category_id')->label('Add these articles also to')->options(fn () => Category::leafChoices())->searchable()->required()])
                        ->action(function ($records, array $data) {
                            foreach ($records as $article) {
                                if ($article->category_id !== (int) $data['category_id']) {
                                    $article->extraCategories()->syncWithoutDetaching([(int) $data['category_id']]);
                                }
                            }
                            Notification::make()->success()->title('Done. '.count($records).' article(s) assigned.')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
