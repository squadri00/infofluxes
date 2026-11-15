<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Backups;
use App\Support\Backup\BackupManager;
use Filament\Widgets\Widget;

/** On the dashboard, only for the super user: a warning when there is no recent backup. Nothing is shown when all is well. */
class BackupStatus extends Widget
{
    protected string $view = 'filament.widgets.backup-status';

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (auth()->user()?->isSuperuser() ?? false) && BackupManager::isStale();
    }

    public function url(): string
    {
        return Backups::getUrl();
    }

    public function lastText(): string
    {
        $last = BackupManager::last('database');

        return $last ? 'The last database backup was on '.$last->format('j M Y, H:i').'.' : 'There is no database backup yet.';
    }
}
