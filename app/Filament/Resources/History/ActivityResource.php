<?php

namespace App\Filament\Resources\History;

use App\Filament\Resources\History\Pages\ListActivities;
use App\Models\Activity;
use App\Support\History\Present;
use App\Support\History\Undo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** History: who changed what, and when. Only the super user. A change to the words of a record can be put back. */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'History';

    protected static ?string $modelLabel = 'history line';

    protected static ?string $slug = 'history';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperuser() ?? false;
    }

    // The history is a record. Nobody writes, changes or deletes a line here.
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    /** The edit page of the record (or its list), when the record still exists. */
    public static function recordUrl(Activity $a): ?string
    {
        if (! $a->subject()) {
            return null;
        }
        try {
            $resource = Filament::getModelResource($a->subject_type);
            if (! $resource) {
                return null;
            }

            return $resource::hasPage('edit') ? $resource::getUrl('edit', ['record' => $a->subject_id]) : $resource::getUrl('index');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('who')->label('Who')->state(fn (Activity $r) => $r->who()),
                TextColumn::make('event')->label('What')->badge()->formatStateUsing(fn ($state) => Activity::EVENTS[$state] ?? $state)
                    ->color(fn ($state) => ['created' => 'success', 'updated' => 'info', 'deleted' => 'warning', 'restored' => 'success', 'purged' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('subject_type')->label('Kind')->state(fn (Activity $r) => $r->subjectName()),
                TextColumn::make('subject_label')->label('Record')->searchable()->limit(60)->url(fn (Activity $r) => static::recordUrl($r)),
                TextColumn::make('summary')->label('Changed')->state(fn (Activity $r) => Present::summary($r))->wrap()->limit(90),
                TextColumn::make('source')->label('From')->formatStateUsing(fn ($state) => $state === 'command' ? 'A command' : 'The website')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->label('What')->options(Activity::EVENTS),
                SelectFilter::make('subject_type')->label('Kind of record')->options(fn () => Activity::query()->distinct()->pluck('subject_type')
                    ->mapWithKeys(fn ($t) => [$t => str(class_basename($t))->snake(' ')->title()->toString()])->all()),
                SelectFilter::make('user_id')->label('Who')->options(fn () => \App\Models\User::query()
                    ->whereIn('id', Activity::query()->whereNotNull('user_id')->distinct()->pluck('user_id')->all())->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('when')->label('Date')
                    ->schema([DatePicker::make('from')->label('From'), DatePicker::make('until')->label('Until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))),
            ])
            ->recordActions([
                Action::make('details')->label('Details')->icon('heroicon-o-eye')
                    ->modalHeading(fn (Activity $r) => Activity::EVENTS[$r->event].': '.$r->subject_label)
                    ->modalContent(fn (Activity $r) => view('filament.history.details', ['a' => $r]))
                    ->modalWidth('5xl')->modalSubmitAction(false)->modalCancelActionLabel('Close'),
                Action::make('undo')->label('Put the old words back')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (Activity $r) => Undo::fields($r) !== [])
                    ->modalHeading('Put the old words back')
                    ->modalDescription('The record gets the words it had before this change. This is written to the history too, so you can change your mind later.')
                    ->schema(fn (Activity $r) => [
                        CheckboxList::make('fields')->label('Which fields?')->required()
                            ->options(fn () => collect(Undo::fields($r))->map(fn ($name, $field) => $name.(Undo::changedSince($r, $field) ? ' (changed again after this line: that later change is lost too)' : ''))->all())
                            ->default(array_keys(Undo::fields($r))),
                    ])
                    ->action(function (Activity $r, array $data) {
                        $done = Undo::apply($r, (array) ($data['fields'] ?? []));
                        Notification::make()->success()->title($done['restored'] ? 'The old words are back.' : 'Nothing was changed.')
                            ->body($done['restored'] ? implode(', ', array_map(fn ($f) => Present::fieldName($f), $done['restored'])) : null)->send();
                    }),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivities::route('/')];
    }
}
