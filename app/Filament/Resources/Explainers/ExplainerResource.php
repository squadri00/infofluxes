<?php

namespace App\Filament\Resources\Explainers;

use App\Filament\Resources\Explainers\Pages\ManageExplainers;
use App\Filament\Support\Translated;
use App\Models\Explainer;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Explainers (word notes): a short note about a word, a person, a place or an event. Write it once, then call it in any article with @[the word].
 * A reader taps the word and sees the note. A note made by the AI import starts as a draft, and is shown to readers only when it is published.
 */
class ExplainerResource extends Resource
{
    protected static ?string $model = Explainer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Explainers';

    protected static ?string $modelLabel = 'explainer';

    protected static ?int $navigationSort = 9;

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('explainers') ?? false;
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }
    public static function canEdit(Model $record): bool { return static::allowed(); }
    public static function canDelete(Model $record): bool { return static::allowed(); }
    public static function canRestore(Model $record): bool { return static::allowed(); }
    public static function canDeleteAny(): bool { return static::allowed(); }
    public static function canForceDelete(Model $record): bool { return auth()->user()?->isSuperuser() ?? false; }

    /** An explainer written with AI help is published only after a person writes their name in "Reviewer". */
    public static function applyRules(array $data, ?Explainer $record = null): array
    {
        $aiAssisted = $record ? (bool) $record->ai_assisted : false;
        $becomesPublished = ($data['status'] ?? 'draft') === 'published' && ($record?->status !== 'published');
        if ($becomesPublished && $aiAssisted && blank($data['reviewer_name'] ?? null)) {
            Notification::make()->danger()->title('Not published')->body('This note was written with AI help. Write the name of the person who read it in "Reviewer name", then publish.')->persistent()->send();
            $data['status'] = $record?->status ?? 'draft';
        }
        $data['author_id'] = $record?->author_id ?? auth()->id();

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...Translated::text('term', 'Word, name or place', required: true, max: 80, help: 'Exactly as it is written in the article, for example Taghut or Sidrat al-Muntaha.'),
            Select::make('kind')->label('What it is')->options(Explainer::KINDS)->default('word')->required()->native(true),
            TagsInput::make('aliases')->label('Other spellings (optional)')->helperText('Other ways the same word is written, for example Taghoot. The note answers to all of them.'),
            ...Translated::textarea('body', 'The note', required: true, rows: 3, help: 'One or two simple sentences. Say what it is, fairly, in plain words. A salutation goes after names (ﷺ, (AS), (RA), (SWT)).'),
            TextInput::make('source_line')->label('Source (optional)')->maxLength(500)->helperText('Where this was read: a book, a page or a web address.'),
            Select::make('status')->label('Status')->options(Explainer::STATUSES)->default('draft')->required()->native(true),
            TextInput::make('reviewer_name')->label('Reviewer name')->maxLength(255)->helperText('The person who checked it. Needed before a note written with AI help can be published.'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->label('ID')->state(fn (Explainer $r) => $r->code)->sortable(query: fn (Builder $query, string $dir) => $query->orderBy('id', $dir))->weight('bold'),
                TextColumn::make('term')->label('Word')->state(fn (Explainer $r) => $r->mainTerm())->searchable(query: fn (Builder $query, string $search) => $query->where('term', 'like', '%'.$search.'%')->orWhere('aliases', 'like', '%'.$search.'%')),
                TextColumn::make('kind')->label('Kind')->formatStateUsing(fn ($state) => Explainer::KINDS[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('note')->label('Note')->state(fn (Explainer $r) => strip_tags((string) $r->getTranslation('body', config('infofluxes.default_locale'), true)))->limit(80)->wrap(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => Explainer::STATUSES[$state] ?? $state)->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                IconColumn::make('ai_assisted')->label('AI help')->boolean()->trueIcon('heroicon-o-sparkles')->falseIcon('heroicon-o-minus')->trueColor('warning'),
                TextColumn::make('reviewer_name')->label('Reviewer')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Explainer::STATUSES),
                SelectFilter::make('kind')->options(Explainer::KINDS),
                \Filament\Tables\Filters\TernaryFilter::make('ai_assisted')->label('Written with AI help'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the explainer')->mutateDataUsing(fn (array $data, Explainer $record) => static::applyRules($data, $record)),
                DeleteAction::make()->label('Delete'),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('publish')->label('Checked: publish')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn () => static::allowed())
                    ->requiresConfirmation()->modalDescription('Only press this after you have read each note. Your name is written as the reviewer of the ones written with AI help.')
                    ->schema([TextInput::make('reviewer_name')->label('Reviewer name')->required()->default(fn () => auth()->user()?->name)->maxLength(255)])
                    ->action(function (Collection $records, array $data) {
                        $n = 0;
                        foreach ($records as $e) {
                            $e->update(['status' => 'published', 'reviewer_name' => filled($e->reviewer_name) ? $e->reviewer_name : $data['reviewer_name']]);
                            $n++;
                        }
                        Notification::make()->success()->title($n.' note(s) published.')->send();
                    })->deselectRecordsAfterCompletion(),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageExplainers::route('/')];
    }
}
