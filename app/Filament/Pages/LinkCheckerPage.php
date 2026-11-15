<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\LinkCheck;
use App\Support\LinkSweep;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Link checker: do the source links of all articles still open? Only a moderator or the super user.
 * A few links are checked every hour by themselves, and "Check all links now" checks every one, a few at a time, with a progress bar.
 * A broken link never hides an article; it only shows here and in the Articles list.
 */
class LinkCheckerPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Link checker';

    protected static ?string $title = 'Link checker';

    protected static ?string $slug = 'link-checker';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.link-checker';

    public bool $sweeping = false;

    public int $sweepTotal = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->canPublish() ?? false;
    }

    public function mount(): void
    {
        LinkSweep::sync();
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $all = LinkCheck::query()->where('ignored', false);

        return [
            'total' => (clone $all)->count(),
            'ok' => (clone $all)->where('status', 'ok')->count(),
            'broken' => (clone $all)->where('status', 'broken')->count(),
            'blocked' => (clone $all)->where('status', 'blocked')->whereNull('manual_ok_at')->count(),
            'new' => (clone $all)->where('status', 'new')->count(),
        ];
    }

    public function startSweep(): void
    {
        abort_unless(static::canAccess(), 403);
        LinkSweep::sync();
        $this->sweepTotal = LinkSweep::queueAll();
        $this->sweeping = $this->sweepTotal > 0;
        if (! $this->sweeping) {
            Notification::make()->warning()->title('There are no source links to check yet.')->send();
        }
    }

    /** Called every few seconds by the page while a check is running. */
    public function step(): void
    {
        abort_unless(static::canAccess(), 403);
        if (! $this->sweeping) {
            return;
        }
        LinkSweep::step(3, 20);
        if (LinkSweep::progress()['queued'] === 0) {
            $this->sweeping = false;
            $c = $this->counts();
            Notification::make()->success()->title('All links were checked.')->body($c['broken'].' broken, '.$c['blocked'].' blocked (open them yourself), '.$c['ok'].' open.')->send();
        }
    }

    public function progressText(): string
    {
        $p = LinkSweep::progress();
        $done = max(0, min($this->sweepTotal, $this->sweepTotal - $p['queued']));

        return "Checked {$done} of {$this->sweepTotal} links.";
    }

    public function progressPercent(): int
    {
        $p = LinkSweep::progress();

        return $this->sweepTotal > 0 ? (int) round(100 * ($this->sweepTotal - $p['queued']) / $this->sweepTotal) : 0;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LinkCheck::query()->with('article'))
            ->defaultSort('failed_since')
            ->columns([
                TextColumn::make('article')->label('Article')->state(fn (LinkCheck $r) => $r->article ? $r->article->code.' '.$r->article->mainTitle() : '—')
                    ->url(fn (LinkCheck $r) => $r->article ? ArticleResource::getUrl('edit', ['record' => $r->article_id]) : null)->wrap()->limit(60),
                TextColumn::make('ref')->label('Source')->wrap()->limit(70)->searchable(),
                TextColumn::make('url')->label('Link')->limit(50)->url(fn (LinkCheck $r) => $r->url, true)->searchable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (LinkCheck $r) => $r->shownStatus())
                    ->formatStateUsing(fn ($state, LinkCheck $r) => match ($state) {
                        'by_hand' => 'Checked by hand '.$r->manual_ok_at?->format('j M Y'),
                        default => LinkCheck::STATUSES[$state] ?? $state,
                    })
                    ->color(fn ($state) => ['ok' => 'success', 'broken' => 'danger', 'blocked' => 'warning', 'by_hand' => 'success', 'new' => 'gray', 'skipped' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('http_status')->label('Answer')->placeholder('—')->toggleable(),
                TextColumn::make('checked_at')->label('Last checked')->since()->placeholder('never')->sortable(),
                TextColumn::make('failed_since')->label('Failing since')->since()->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('view')->label('Show')->selectablePlaceholder(false)->default('attention')
                    ->options(['attention' => 'Links that need a person', 'all' => 'All links', 'broken' => 'Broken only', 'by_hand' => 'Checked by hand', 'ignored' => 'Ignored links'])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? 'attention') {
                            'all' => $query->where('ignored', false),
                            'broken' => $query->broken(),
                            'by_hand' => $query->where('ignored', false)->whereNotNull('manual_ok_at'),
                            'ignored' => $query->where('ignored', true),
                            default => $query->needsAttention(),
                        };
                    }),
                SelectFilter::make('status')->label('Status')->options(LinkCheck::STATUSES),
            ])
            ->recordActions([
                Action::make('check')->label('Check now')->icon('heroicon-o-arrow-path')->color('gray')
                    ->action(function (LinkCheck $r) {
                        $r = LinkSweep::checkRow($r);
                        Notification::make()->title(LinkCheck::STATUSES[$r->status] ?? $r->status)->body($r->url)->send();
                    }),
                Action::make('hand')->label('I opened it: it is fine')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (LinkCheck $r) => in_array($r->status, ['blocked', 'broken'], true) && ! $r->manual_ok_at)
                    ->requiresConfirmation()->modalDescription('Open the link yourself first. Only press this if the page opened and says what the article says.')
                    ->action(fn (LinkCheck $r) => LinkSweep::markByHand($r, auth()->id())),
                Action::make('unhand')->label('Take away "checked by hand"')->icon('heroicon-o-x-circle')->color('gray')
                    ->visible(fn (LinkCheck $r) => (bool) $r->manual_ok_at)
                    ->action(fn (LinkCheck $r) => $r->update(['manual_ok_at' => null, 'manual_ok_by' => null])),
                Action::make('ignore')->label('Ignore')->icon('heroicon-o-eye-slash')->color('gray')
                    ->visible(fn (LinkCheck $r) => ! $r->ignored)
                    ->action(fn (LinkCheck $r) => $r->update(['ignored' => true, 'queued' => false])),
                Action::make('unignore')->label('Stop ignoring')->icon('heroicon-o-eye')->color('gray')
                    ->visible(fn (LinkCheck $r) => $r->ignored)
                    ->action(fn (LinkCheck $r) => $r->update(['ignored' => false])),
            ])
            ->paginated([25, 50, 100]);
    }
}
