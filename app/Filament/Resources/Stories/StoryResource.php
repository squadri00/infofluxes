<?php

namespace App\Filament\Resources\Stories;

use App\Filament\Resources\Stories\Pages\CreateStory;
use App\Filament\Resources\Stories\Pages\EditStory;
use App\Filament\Resources\Stories\Pages\ListStories;
use App\Filament\Support\Translated;
use App\Models\Category;
use App\Models\Story;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Visual Stories: picture stories told in chapters and slides (code S-1, S-2...). Their own list, their own edit page,
 * apart from the articles. The same people who work on articles work on stories, with the same rules.
 */
class StoryResource extends Resource
{
    protected static ?string $model = Story::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Visual Stories';

    protected static ?string $modelLabel = 'story';

    protected static ?string $pluralModelLabel = 'visual stories';

    protected static ?int $navigationSort = 2;

    private static function screen(): bool
    {
        return auth()->user()?->canUseScreen('stories') ?? false;
    }

    /** A moderator or the super user works on every story; an author only on their own. */
    private static function mine(Model $record): bool
    {
        $user = auth()->user();

        return static::screen() && ($user->canPublish() || (int) $record->author_id === $user->id);
    }

    public static function canViewAny(): bool { return static::screen(); }
    public static function canCreate(): bool { return static::screen(); }
    public static function canReplicate(Model $record): bool { return static::mine($record); }
    public static function canEdit(Model $record): bool { return static::mine($record); }
    public static function canRestore(Model $record): bool { return static::mine($record); }
    public static function canDeleteAny(): bool { return static::screen(); }

    /** An author may delete only their own stories that are not published yet. */
    public static function canDelete(Model $record): bool
    {
        return static::mine($record) && (auth()->user()->canPublish() || in_array($record->status, ['draft', 'in_review'], true));
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
            ? Story::STATUSES
            : array_intersect_key(Story::STATUSES, array_flip(['draft', 'in_review']));
    }

    public static function form(Schema $schema): Schema
    {
        $canPublish = fn () => ! auth()->user()?->canPublish();
        $audioState = fn (string $name) => Select::make($name)->label('Audio state')->options(['draft' => 'Draft: not played yet', 'approved' => 'Approved: it is played'])->default('draft')->dehydrateStateUsing(fn ($state) => $state ?: 'draft')
            ->helperText('Listen plays the file only when it is Approved. A new file starts as Draft. Only a moderator or the super user can approve.')
            ->disabled($canPublish)->dehydrated(fn () => (bool) auth()->user()?->canPublish());

        return $schema
            ->columns(1)
            ->components([
                Section::make('The story')
                    ->collapsible()
                    ->collapsed(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        TextInput::make('code_shown')
                            ->label('Code')
                            ->helperText('Made by the system. It never changes.')
                            ->disabled()->dehydrated(false)
                            ->afterStateHydrated(fn (TextInput $component, ?Story $record) => $component->state($record?->code))
                            ->visible(fn (?Story $record) => $record !== null),
                        ...Translated::text('title', 'Title', required: true),
                        Select::make('category_id')
                            ->label('Main category')
                            ->helperText('The code of each category shows in the list, for example (C-12).')
                            ->options(fn () => Category::choices())
                            ->searchable()
                            ->required(),
                        Select::make('extraCategories')
                            ->label('Also in these categories (optional)')
                            ->relationship('extraCategories', 'id')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::searchCategories($search))
                            ->getOptionLabelsUsing(fn (array $values): array => array_intersect_key(Category::choices(), array_flip($values)))
                            ->helperText('Type part of a name, or a code such as C-12, to find a category.'),
                        ...Translated::textarea('short_detail', 'Short detail', rows: 3, help: 'One or two sentences. Shown on the cover of the story and on its card.'),
                        ...Translated::rich('long_detail', 'Text under the story (optional)'),
                        TagsInput::make('search_words')->label('Search words')->helperText('Type a word or phrase and press Enter. Add as many as you like.')->columnSpanFull(),
                        TextInput::make('source_line')->label('Source (optional)')->helperText('One line. It shows on the page only when it is filled in.')->maxLength(500),
                    ])->columns(1),

                Section::make('Cover picture')
                    ->collapsed(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        FileUpload::make('main_image')->label('Cover picture')->image()
                            ->acceptedFileTypes(\App\Support\StoryImage::TYPES)->disk(\App\Support\StoryImage::DISK)->directory(\App\Support\StoryImage::DIRECTORY)
                            ->imageEditor()->imageCropAspectRatio(fn () => \App\Support\StoryImage::ratioText())->imageEditorAspectRatioOptions(fn () => [\App\Support\StoryImage::ratioText()])
                            ->maxSize(fn () => \App\Support\ImageStore::maxKb())
                            ->saveUploadedFileUsing(fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string => \App\Support\StoryImage::store($file))
                            ->helperText('It is cut to the shape set for the site (16:9) and saved as a small WebP. It is the cover of the slides and the picture on the card.'),
                        ...Translated::text('main_image_alt', 'Describe the cover picture (for people who cannot see it)'),
                        TextInput::make('main_image_credit')->label('Picture credit')->helperText('Left empty, the story viewer shows "AI Generated".')->maxLength(255),
                        TextInput::make('main_image_licence')->label('Picture licence (optional)')->helperText('For example: Public domain, CC BY-SA 4.0.')->maxLength(255),
                    ])->collapsible(),

                Section::make('Chapters and slides')
                    ->description('Add chapters, and slides inside them. The slide viewer shows the cover first, then each slide. A slide has a picture (you can leave it out), a heading and about 25 words. NEVER show or draw the Prophets (AS) or the Prophet Muhammad ﷺ. Allowed pictures: maps, objects, places, inscriptions, calligraphy and plain diagrams.')
                    ->collapsible()
                    ->schema([
                        Repeater::make('chapters')
                            ->relationship('chapters')
                            ->orderColumn('sort_order')
                            ->hiddenLabel()
                            ->addActionLabel('Add a chapter')
                            ->defaultItems(0)
                            ->collapsible()->collapsed()
                            ->itemLabel(fn (array $state) => $state['title'][config('infofluxes.default_locale')] ?? 'Chapter')
                            ->schema([
                                ...Translated::text('title', 'Chapter name', required: true),
                                Repeater::make('slides')
                                    ->relationship('slides')
                                    ->orderColumn('sort_order')
                                    ->label('Slides')
                                    ->addActionLabel('Add a slide')
                                    ->defaultItems(0)
                                    ->collapsible()->collapsed()
                                    ->itemLabel(fn (array $state) => $state['heading'][config('infofluxes.default_locale')] ?? 'Slide')
                                    ->schema([
                                        FileUpload::make('image')->label('Picture (you can leave it out)')->image()
                                            ->acceptedFileTypes(\App\Support\StoryImage::TYPES)->disk(\App\Support\StoryImage::DISK)->directory(\App\Support\StoryImage::DIRECTORY)
                                            ->imageEditor()->imageCropAspectRatio(fn () => \App\Support\StoryImage::ratioText())->imageEditorAspectRatioOptions(fn () => [\App\Support\StoryImage::ratioText()])
                                            ->maxSize(fn () => \App\Support\ImageStore::maxKb())
                                            ->saveUploadedFileUsing(fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string => \App\Support\StoryImage::store($file)),
                                        ...Translated::text('image_alt', 'Describe the picture (for people who cannot see it)'),
                                        TextInput::make('image_credit')->label('Picture credit')->helperText('Left empty, the viewer shows "AI Generated".')->maxLength(255),
                                        TextInput::make('image_licence')->label('Picture licence (optional)')->maxLength(255),
                                        ...Translated::text('heading', 'Heading', required: true),
                                        ...Translated::textarea('body', 'Text (about 25 words)', rows: 3, help: 'Plain words. A blank line starts a new paragraph.'),
                                        FileUpload::make('audio')->label('Audio of this slide (optional)')->disk('public')->directory('audio/slides')->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])->maxSize(30720)->helperText('An MP3 file, up to 30 MB.'),
                                        $audioState('audio_state'),
                                    ]),
                            ]),
                    ]),

                Section::make('Audio for the Listen button')
                    ->description('Optional. Without a file, the Listen button reads the words aloud with the voice of the visitor\'s own device (free). A file plays instead, but only once it is Approved. A slide has its own audio box inside the chapters.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        FileUpload::make('cover_audio')->label('Audio of the cover (optional)')->disk('public')->directory('audio/covers')->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])->maxSize(30720)->helperText('An MP3 file, up to 30 MB.'),
                        $audioState('cover_audio_state'),
                        FileUpload::make('audio')->label('One audio file for the whole story (optional)')->disk('public')->directory('audio/stories')->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])->maxSize(30720)->helperText('An MP3 file, up to 30 MB.'),
                        $audioState('audio_state'),
                    ])->columns(2),

                Section::make('Publishing')
                    ->collapsible()
                    ->collapsed(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        Select::make('status')->options(fn () => static::statusOptions())->default('draft')->required(),
                        DateTimePicker::make('publish_date')->label('Publish date')->helperText('Left empty, it is the moment you publish. A date in the future keeps the story hidden until then.'),
                        Select::make('author_id')->label('Author')->relationship('author', 'name')->default(fn () => auth()->id())->searchable()->preload()->disabled($canPublish)->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        Select::make('contributor_id')->label('Contributor (credited on the story)')->relationship('contributor', 'name')->searchable()->preload()
                            ->helperText('A person from Content > Contributors. Their name shows under the title and links to their page when they have one.')
                            ->disabled($canPublish)->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        Toggle::make('is_featured')->label('Featured on the home page')->live()
                            ->helperText('A featured story shows in the Stories in pictures part of the home page (when it is published).')
                            ->disabled($canPublish)->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        TextInput::make('featured_order')->label('Featured order')->numeric()->minValue(0)->default(0)
                            ->helperText('1 comes first, then 2, and so on. 0 means after the numbered ones.')
                            ->visible(fn (Get $get) => (bool) $get('is_featured'))
                            ->disabled($canPublish)->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        TextInput::make('reviewer_name')->label('Reviewer name')->helperText('The person who checked it. Needed before a story written with AI help can be published.')->maxLength(255),
                        Toggle::make('ai_assisted')->label('Written with AI help')->helperText('Leave it on until a person has read and checked the story.')->disabled($canPublish)->dehydrated(fn () => (bool) auth()->user()?->canPublish()),
                        TextInput::make('slug')->label('Web address (short name)')->helperText('Left empty, it is made from the title. The story opens at /stories/this-name.')->maxLength(255)
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
                    ->description('Leave empty and the title and short detail are used. The meta title and description are what Google shows; the AI import fills them. The page title, if you write one, is used in the browser tab instead.')
                    ->schema([
                        ...Translated::text('page_title', 'Page title'),
                        ...Translated::text('meta_title', 'Meta title (for Google, up to 60 letters)'),
                        ...Translated::textarea('meta_description', 'Meta description', rows: 2),
                    ]),
            ]);
    }

    /** The categories whose path or code contains the typed words (at most 50). Done in memory, with no database search. */
    public static function searchCategories(string $search): array
    {
        $words = array_filter(preg_split('/\s+/u', mb_strtolower(trim($search))));
        $found = [];
        foreach (Category::choices() as $id => $label) {
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'extraCategories', 'author'])->withCount('slides'))
            ->columns([
                TextColumn::make('id')->label('ID')->state(fn (Story $record) => $record->code)->sortable()->weight('bold'),
                TextColumn::make('reactions')->label('Likes / dislikes')->state(fn (Story $record) => $record->likesCount().' / '.$record->dislikesCount())->toggleable(),
                ImageColumn::make('main_image')->label('Cover')->disk('public')->height(36)->width(64),
                TextColumn::make('title')->label('Title')
                    ->state(fn (Story $record) => $record->mainTitle())
                    ->description(fn (Story $record) => $record->ai_assisted ? '✨ AI' : null)
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($q) => $q
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('search_words', 'like', "%{$search}%")
                        ->orWhere('id', ltrim(strtoupper($search), 'S-') ?: 0))),
                TextColumn::make('categories_shown')->label('Categories')
                    ->state(fn (Story $record) => $record->allCategories()->map(fn ($c) => $c->mainName().' ('.$c->code.')')->implode('  ·  '))
                    ->color('gray')->size('xs')->wrap(),
                TextColumn::make('slides_count')->label('Slides')->sortable(),
                \Filament\Tables\Columns\ToggleColumn::make('is_featured')->label('Featured')->onIcon('heroicon-s-star')->offIcon('heroicon-o-star')->onColor('warning')
                    ->disabled(fn () => ! auth()->user()?->canPublish()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Story::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['published' => 'success', 'in_review' => 'warning', 'draft' => 'gray', 'archived' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('author.name')->label('Author')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('publish_date')->label('Publish date')->date('j M Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Story::STATUSES),
                TernaryFilter::make('is_featured')->label('Featured on the home page'),
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
                Action::make('view')->label('View')->icon('heroicon-o-eye')->color('gray')
                    ->url(fn (Story $record) => $record->url(), shouldOpenInNewTab: true),
                ReplicateAction::make()
                    ->label('Clone')
                    ->excludeAttributes(['slug', 'is_featured', 'featured_order', 'slides_count'])
                    ->beforeReplicaSaved(function (Story $replica, Story $record) {
                        $replica->setTranslations('title', array_map(fn ($t) => $t.' (copy)', $record->getTranslations('title')));
                        $replica->slug = Str::slug($record->mainTitle()).'-copy-'.Str::lower(Str::random(4));
                        $replica->status = 'draft';
                        $replica->author_id = auth()->id();
                    })
                    ->after(function (Story $replica, Story $record) {
                        // The copy also sits in the same other categories and has the same chapters and slides.
                        $replica->extraCategories()->sync($record->extraCategories->pluck('id')->all());
                        foreach ($record->chapters()->with('slides')->get() as $chapter) {
                            $newChapter = $replica->chapters()->create(['title' => $chapter->getTranslations('title'), 'sort_order' => $chapter->sort_order]);
                            foreach ($chapter->slides as $slide) {
                                $newChapter->slides()->create($slide->only(['sort_order', 'image', 'image_credit', 'image_licence']) + [
                                    'image_alt' => $slide->getTranslations('image_alt'), 'heading' => $slide->getTranslations('heading'), 'body' => $slide->getTranslations('body'),
                                ]);
                            }
                        }
                    }),
                DeleteAction::make()->label('Delete')->modalDescription('The story goes to the Trash. You can bring it back from there.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign')
                        ->visible(fn () => (bool) auth()->user()?->canPublish())
                        ->label('Assign to a category')
                        ->icon('heroicon-o-folder-plus')
                        ->schema([Select::make('category_id')->label('Add these stories also to')->options(fn () => Category::choices())->searchable()->required()])
                        ->action(function ($records, array $data) {
                            foreach ($records as $story) {
                                if ($story->category_id !== (int) $data['category_id']) {
                                    $story->extraCategories()->syncWithoutDetaching([(int) $data['category_id']]);
                                }
                            }
                            Notification::make()->success()->title('Done. '.count($records).' story(ies) assigned.')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStories::route('/'),
            'create' => CreateStory::route('/create'),
            'edit' => EditStory::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
