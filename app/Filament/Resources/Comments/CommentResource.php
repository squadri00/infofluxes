<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\Pages\ListComments;
use App\Models\Comment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Comments: approve, hide, reply or delete. A reply from staff is shown at once. */
class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'comment';

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('comments') ?? false;
    }

    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return static::allowed(); }
    public static function canDeleteAny(): bool { return static::allowed(); }

    public static function getNavigationBadge(): ?string
    {
        $n = Comment::where('status', 'pending')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['article', 'story', 'user', 'parent']))
            ->columns([
                TextColumn::make('body')->label('Comment')->limit(140)->wrap()->searchable()
                    ->description(fn (Comment $record) => $record->parent_id ? 'A reply' : null),
                TextColumn::make('article')->label('Article or story')
                    ->state(fn (Comment $record) => $record->story
                        ? $record->story->mainTitle().' ('.$record->story->code.')'
                        : ($record->article ? $record->article->mainTitle().' ('.$record->article->code.')' : '-'))
                    ->description(fn (Comment $record) => $record->story ? 'Story' : null)
                    ->url(fn (Comment $record) => $record->story ? $record->story->url() : ($record->article ? route('article.show', $record->article->slug) : null), true)
                    ->limit(40),
                TextColumn::make('user.name')->label('By')->placeholder('(removed)'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Comment::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['approved' => 'success', 'pending' => 'warning', 'hidden' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('created_at')->label('When')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Comment::STATUSES)->default('pending'),
            ])
            ->recordActions([
                Action::make('approve')->label('Approve')->color('success')
                    ->visible(fn (Comment $record) => $record->status !== 'approved')
                    ->action(fn (Comment $record) => $record->update(['status' => 'approved'])),
                Action::make('hide')->label('Hide')->color('gray')
                    ->visible(fn (Comment $record) => $record->status !== 'hidden')
                    ->action(fn (Comment $record) => $record->update(['status' => 'hidden'])),
                Action::make('reply')->label('Reply')
                    ->schema([Textarea::make('body')->label('Your reply')->required()->maxLength(2000)->rows(4)])
                    ->action(function (Comment $record, array $data) {
                        // A reply is always shown under the first comment of the thread, and from staff it is shown at once.
                        Comment::create([
                            'article_id' => $record->article_id,
                            'story_id' => $record->story_id,
                            'user_id' => auth()->id(),
                            'parent_id' => $record->parent_id ?? $record->id,
                            'body' => $data['body'],
                            'status' => 'approved',
                        ]);
                        if ($record->status === 'pending') {
                            $record->update(['status' => 'approved']);
                        }
                        Notification::make()->success()->title('Reply posted.')->send();
                    }),
                DeleteAction::make()->label('Delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveAll')->label('Approve')->action(fn ($records) => $records->each->update(['status' => 'approved']))->deselectRecordsAfterCompletion(),
                    BulkAction::make('hideAll')->label('Hide')->action(fn ($records) => $records->each->update(['status' => 'hidden']))->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListComments::route('/')];
    }
}
