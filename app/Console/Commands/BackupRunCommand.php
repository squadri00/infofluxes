<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupManager;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run {--only=all : all, database or files}';

    protected $description = 'Make a backup of the database and of the uploaded files, then remove the copies older than the keep time.';

    public function handle(): int
    {
        $only = in_array($this->option('only'), ['database', 'files'], true) ? $this->option('only') : 'all';
        $r = BackupManager::run($only);

        foreach ($r['made'] as $name) {
            $this->info('Made: '.$name.' ('.BackupManager::human((int) filesize(BackupManager::path($name))).')');
        }
        if ($r['removed'] > 0) {
            $this->line('Removed '.$r['removed'].' old copy(ies) (older than '.BackupManager::keepDays().' days).');
        }
        foreach ($r['errors'] as $e) {
            $this->error($e);
        }

        return $r['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
