<?php

namespace App\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The common part of the admin screens of "Challenge yourself" (the questions of the five tests).
 * Each list is one page: add, modify, clone and delete happen in a small window. Who may use them is the "Challenge tests" tick.
 */
abstract class ChallengeResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Challenge yourself';

    protected static function allowed(): bool
    {
        return auth()->user()?->canUseScreen('tests') ?? false;
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

    /** The two flags every question has, as small ticks in the list. */
    protected static function flagColumns(): array
    {
        return [
            IconColumn::make('is_active')->label('On')->boolean(),
            IconColumn::make('is_checked')->label('Checked')->boolean()->tooltip('Checked against a printed edition or a reliable source by a person'),
        ];
    }

    /** Modify, Clone, Delete (the M, C and X of the list). */
    protected static function standardActions(Table $table, bool $clone = true, bool $delete = true): Table
    {
        $actions = [EditAction::make()->label('Modify')->modalHeading('Modify')];
        if ($clone) {
            $actions[] = ReplicateAction::make()->label('Clone')->excludeAttributes(['times_shown', 'times_picked', 'times_correct'])->successNotificationTitle('Cloned. Now modify the copy.');
        }
        if ($delete) {
            $actions[] = DeleteAction::make()->label('Delete');
        }

        return $table->recordActions($actions)->paginated([25, 50, 100])->defaultPaginationPageOption(25);
    }
}
