<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Backup\BackupManager;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Backups: make a copy now, download it, delete it, and set how long copies are kept. Only the super user.
 * The nightly copy is made by "php artisan backup:run" (see the instructions on the page).
 */
class Backups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Backups';

    protected static ?string $title = 'Backups';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.backups';

    public int $keepDays = BackupManager::DEFAULT_KEEP_DAYS;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperuser() ?? false;
    }

    public function mount(): void
    {
        $this->keepDays = BackupManager::keepDays();
    }

    /** @return list<array{name: string, kind: string, bytes: int, made_at: \Carbon\Carbon}> */
    public function copies(): array
    {
        return BackupManager::all();
    }

    public function status(): array
    {
        return [
            'database' => BackupManager::last('database'),
            'files' => BackupManager::last('files'),
            'used' => BackupManager::human(BackupManager::usedBytes()),
            'count' => count(BackupManager::all()),
            'stale' => BackupManager::isStale(),
            'last_run' => Setting::get('backup_last_run'),
            'last_errors' => (string) Setting::get('backup_last_errors', ''),
        ];
    }

    public function backupDatabase(): void
    {
        $this->makeCopy('database');
    }

    public function backupFiles(): void
    {
        $this->makeCopy('files');
    }

    private function makeCopy(string $only): void
    {
        abort_unless(static::canAccess(), 403);
        $r = BackupManager::run($only);
        if ($r['errors'] !== []) {
            Notification::make()->danger()->title('The backup did not work.')->body(implode(' ', $r['errors']))->persistent()->send();

            return;
        }
        Notification::make()->success()->title('Backup made.')->body($r['made'][0] ?? '')->send();
    }

    public function download(string $name)
    {
        abort_unless(static::canAccess(), 403);
        $path = BackupManager::path($name);
        abort_if($path === null, 404);

        return response()->download($path, $name);
    }

    public function remove(string $name): void
    {
        abort_unless(static::canAccess(), 403);
        if (BackupManager::delete($name)) {
            Notification::make()->success()->title('The copy was deleted.')->send();
        }
    }

    public function saveKeepDays(): void
    {
        abort_unless(static::canAccess(), 403);
        $days = max(1, min(365, (int) $this->keepDays));
        Setting::put('backup_keep_days', $days);
        $this->keepDays = $days;
        $removed = BackupManager::prune();
        Notification::make()->success()->title('Saved.')->body("Copies are kept for {$days} days.".($removed ? " {$removed} old copy(ies) were removed." : ''))->send();
    }

    public function human(int $bytes): string
    {
        return BackupManager::human($bytes);
    }

    /** The one-line command for the scheduled task, with this site's real path. */
    public function artisanPath(): string
    {
        return base_path('artisan');
    }
}
