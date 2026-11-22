<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use App\Support\TextSearch;
use App\Support\TopicAssignments;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Topics to write: the empty subjects (draft index lines) with who has each one. Everybody on the staff sees the list, takes a free subject
 * or gives it to another person, so the same topic is not written twice. The text of other people's articles is never shown here.
 */
class TopicsToWrite extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Organise';

    protected static ?string $navigationLabel = 'Topics to write';

    protected static ?string $title = 'Topics to write';

    protected static ?string $slug = 'topics-to-write';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.topics-to-write';

    public static function canAccess(): bool
    {
        return auth()->user()?->canUseScreen('articles') ?? false;
    }

    private static function topics(): Builder
    {
        return Article::query()->where('status', 'draft')->where('is_index_line', true);
    }

    /** The search box: words (any letter case) in the title, subtopic or working name, an article number (A-42) or a category number (C-12). */
    public static function searchTopics(Builder $query, string $search): Builder
    {
        $term = trim($search);

        return $query->where(function ($w) use ($term) {
            if (preg_match('/^A-?(\d+)$/i', $term, $m)) {
                $w->orWhere('id', (int) $m[1]);
            }
            if (preg_match('/^C-?(\d+)$/i', $term, $m)) {
                $w->orWhere('category_id', (int) $m[1]);
            }
            $w->orWhere(fn ($x) => TextSearch::match($x, ['title', 'custom_data', 'working_name'], $term));
        });
    }

    /** @return array{free: int, mine: int, taken: int} */
    public function counts(): array
    {
        return [
            'free' => static::topics()->whereNull('assigned_to')->count(),
            'mine' => static::topics()->where('assigned_to', auth()->id())->count(),
            'taken' => static::topics()->whereNotNull('assigned_to')->where('assigned_to', '!=', auth()->id())->count(),
        ];
    }

    private function say(string $title, bool $ok = true): void
    {
        $n = Notification::make()->title($title);
        ($ok ? $n->success() : $n->warning())->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::topics()->with(['category', 'assignee']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('id')->label('ID')->state(fn (Article $r) => $r->code)->sortable()->weight('bold'),
                TextColumn::make('title')->label('Topic to write')->state(fn (Article $r) => $r->mainTitle())->wrap()
                    ->searchable(query: fn (Builder $query, string $search) => static::searchTopics($query, $search)),
                TextColumn::make('category')->label('Category')->state(fn (Article $r) => $r->category ? $r->category->mainName().' ('.$r->category->code.')' : '')->color('gray')->wrap(),
                TextColumn::make('subtopic')->label('Subtopic')->state(fn (Article $r) => $r->custom_data['subtopic'] ?? '')->color('gray')->size('xs')->wrap(),
                TextColumn::make('assignee.name')->label('Who has it')->badge()->placeholder('Free')
                    ->color(fn (Article $r) => $r->assigned_to === null ? 'gray' : ((int) $r->assigned_to === auth()->id() ? 'success' : 'warning'))
                    ->description(fn (Article $r) => $r->assigned_at ? 'since '.$r->assigned_at->format('j M Y') : null),
            ])
            ->filters([
                SelectFilter::make('view')->label('Show')->selectablePlaceholder(false)->default('free')
                    ->options(['free' => 'Free topics', 'mine' => 'My topics', 'taken' => 'Taken by other people', 'all' => 'All topics'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? 'free') {
                        'mine' => $query->where('assigned_to', auth()->id()),
                        'taken' => $query->whereNotNull('assigned_to')->where('assigned_to', '!=', auth()->id()),
                        'all' => $query,
                        default => $query->whereNull('assigned_to'),
                    }),
                SelectFilter::make('category_id')->label('Category')->searchable()
                    ->options(fn () => Category::query()->whereIn('id', static::topics()->select('category_id'))->orderBy('tree_path')->get()->mapWithKeys(fn ($c) => [$c->id => $c->mainName().' ('.$c->code.')'])->all()),
                SelectFilter::make('assigned_to')->label('Person')->options(fn () => TopicAssignments::people()),
            ])
            ->recordActions([
                Action::make('take')->label('Take it')->icon('heroicon-o-hand-raised')->color('success')
                    ->visible(fn (Article $r) => $r->assigned_to === null && TopicAssignments::canChange($r, auth()->user()))
                    ->action(function (Article $r) {
                        $ok = TopicAssignments::assign($r, auth()->id(), auth()->user());
                        $this->say($ok ? 'It is yours now.' : 'Somebody else has it.', $ok);
                    }),
                Action::make('assign')->label('Give to…')->icon('heroicon-o-user-plus')->color('gray')
                    ->visible(fn (Article $r) => TopicAssignments::canChange($r, auth()->user()))
                    ->schema([Select::make('user')->label('Give this topic to')->options(fn () => TopicAssignments::people())->searchable()->required()])
                    ->fillForm(fn (Article $r) => ['user' => $r->assigned_to])
                    ->action(function (Article $r, array $data) {
                        $ok = TopicAssignments::assign($r, (int) $data['user'], auth()->user());
                        $this->say($ok ? 'Given.' : 'It is not yours to give.', $ok);
                    }),
                Action::make('free')->label('Hand back')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (Article $r) => $r->assigned_to !== null && TopicAssignments::canChange($r, auth()->user()))
                    ->requiresConfirmation()->modalDescription('The topic becomes free again, so another editor can take it.')
                    ->action(function (Article $r) {
                        TopicAssignments::assign($r, null, auth()->user());
                        $this->say('The topic is free again.');
                    }),
                Action::make('open')->label('Write it')->icon('heroicon-o-pencil-square')
                    ->visible(fn (Article $r) => ArticleResource::canEdit($r))
                    ->url(fn (Article $r) => ArticleResource::getUrl('edit', ['record' => $r->id])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('take_many')->label('Take the ticked topics')->icon('heroicon-o-hand-raised')->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $this->bulk($records, auth()->id())),
                    BulkAction::make('give_many')->label('Give the ticked topics to…')->icon('heroicon-o-user-plus')->deselectRecordsAfterCompletion()
                        ->schema([Select::make('user')->label('Give them to')->options(fn () => TopicAssignments::people())->searchable()->required()])
                        ->action(fn (Collection $records, array $data) => $this->bulk($records, (int) $data['user'])),
                    BulkAction::make('free_many')->label('Hand back the ticked topics')->icon('heroicon-o-arrow-uturn-left')->deselectRecordsAfterCompletion()->requiresConfirmation()
                        ->action(fn (Collection $records) => $this->bulk($records, null)),
                ]),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }

    /** Give many topics at once; the ones somebody else has (and that are not the user's to change) are left alone and counted. */
    private function bulk(Collection $records, ?int $userId): void
    {
        $done = 0;
        $skipped = 0;
        foreach ($records as $record) {
            TopicAssignments::assign($record, $userId, auth()->user()) ? $done++ : $skipped++;
        }
        $this->say($done.' '.($done === 1 ? 'topic' : 'topics').($userId === null ? ' handed back.' : ' assigned.').($skipped ? " {$skipped} left alone: somebody else has them." : ''), $skipped === 0);
    }
}
