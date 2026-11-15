<?php

namespace App\Filament\Resources\Newsletter;

use App\Filament\Resources\Newsletter\Pages\ManageNewsletterIssues;
use App\Models\NewsletterIssue;
use App\Support\Html;
use App\Support\Newsletter;
use App\Support\NewsletterSender;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Write an issue of the newsletter, try it on yourself, and send it to everyone who confirmed their address. */
class NewsletterIssueResource extends Resource
{
    protected static ?string $model = NewsletterIssue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Send newsletter';

    protected static ?string $modelLabel = 'issue';

    protected static ?int $navigationSort = 4;

    private static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('newsletter') ?? false;
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
        return static::allowed() && $record->isDraft();
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed() && $record->isDraft();
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('subject')->label('Subject (the first thing readers see)')->required()->maxLength(200),
            RichEditor::make('body')->label('The newsletter')->required()->columnSpanFull()
                ->helperText('Headings, lists, bold and links are allowed. Every email gets the site name above it and an unsubscribe link below it.'),
        ])->columns(1);
    }

    /** The mail words a person reads ("3 of 12 sent") for the list. */
    private static function progress(NewsletterIssue $issue): string
    {
        if ($issue->isDraft()) {
            return '—';
        }

        return $issue->sent_count.' of '.$issue->recipients_total.' sent'.($issue->failed_count ? ', '.$issue->failed_count.' failed' : '');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('Code')->formatStateUsing(fn ($state) => '[N-'.$state.']')->sortable(),
                TextColumn::make('subject')->searchable()->limit(70),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => NewsletterIssue::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['draft' => 'gray', 'sending' => 'warning', 'sent' => 'success'][$state] ?? 'gray'),
                TextColumn::make('progress')->label('Progress')->state(fn (NewsletterIssue $r) => static::progress($r)),
                TextColumn::make('finished_at')->label('Finished')->dateTime('j M Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('Written')->dateTime('j M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()->label('Modify')->modalHeading('Modify the issue')->modalWidth(Width::FiveExtraLarge)
                    ->mutateDataUsing(fn (array $data) => ['body' => Html::clean((string) ($data['body'] ?? ''))] + $data),
                Action::make('test')->label('Send a test')->icon('heroicon-o-beaker')->color('gray')
                    ->modalHeading('Send a test copy')
                    ->modalDescription('Only this one address gets it. Nothing is recorded and nobody on the list receives anything.')
                    ->schema([TextInput::make('email')->label('Send it to')->email()->required()->default(fn () => auth()->user()?->email)])
                    ->action(function (NewsletterIssue $record, array $data) {
                        NewsletterSender::sendTest($record, (string) $data['email'])
                            ? Notification::make()->success()->title('The test copy was sent.')->send()
                            : Notification::make()->danger()->title('The test copy could not be sent.')->body('Check the mail settings of the site (MAIL_ in the .env file).')->send();
                    }),
                Action::make('send')->label('Send to everyone')->icon('heroicon-o-paper-airplane')->color('success')
                    ->visible(fn (NewsletterIssue $record) => $record->isDraft())
                    ->requiresConfirmation()
                    ->modalHeading('Send this issue?')
                    ->modalDescription(fn () => 'It goes to '.NewsletterSender::audience().' subscribed people, '.NewsletterSender::perRun().' at a time. After you start it, it cannot be changed or taken back.')
                    ->action(function (NewsletterIssue $record) {
                        if (NewsletterSender::audience() === 0) {
                            Notification::make()->warning()->title('Nobody is subscribed yet.')->send();

                            return;
                        }
                        NewsletterSender::start($record);
                        static::report($record->refresh());
                    }),
                Action::make('next')->label('Send the next batch')->icon('heroicon-o-forward')->color('warning')
                    ->visible(fn (NewsletterIssue $record) => $record->status === 'sending')
                    ->action(fn (NewsletterIssue $record) => static::report($record)),
                Action::make('retry')->label('Try the failed ones again')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (NewsletterIssue $record) => ! $record->isDraft() && $record->failed_count > 0)
                    ->requiresConfirmation()
                    ->action(function (NewsletterIssue $record) {
                        $n = NewsletterSender::retryFailed($record);
                        Notification::make()->success()->title($n.' addresses are queued again. Use "Send the next batch".')->send();
                    }),
                ReplicateAction::make()->label('Clone')
                    ->excludeAttributes(['status', 'recipients_total', 'sent_count', 'failed_count', 'started_at', 'finished_at'])
                    ->beforeReplicaSaved(function (NewsletterIssue $replica) {
                        $replica->status = 'draft';
                        $replica->created_by = auth()->id();
                    })
                    ->successNotificationTitle('Cloned as a new draft.'),
                DeleteAction::make()->label('Delete')->visible(fn (NewsletterIssue $record) => $record->isDraft()),
            ])
            ->paginated([25, 50]);
    }

    /** Send a batch and tell the person what happened. */
    private static function report(NewsletterIssue $issue): void
    {
        $result = NewsletterSender::sendBatch($issue);
        $issue->refresh();

        $note = $result['sent'].' sent'.($result['failed'] ? ', '.$result['failed'].' failed' : '').'. ';
        $left = $result['left'] > 0 ? $result['left'].' still to send: use "Send the next batch" (wait a few minutes if your host limits emails).' : 'That was everyone.';

        $notification = Notification::make()->title($note.$left);
        $result['failed'] > 0 ? $notification->warning()->send() : $notification->success()->send();
    }

    public static function getPages(): array
    {
        return ['index' => ManageNewsletterIssues::route('/')];
    }
}
