<?php

namespace App\Console\Commands;

use App\Support\History\HistoryKeeper;
use Illuminate\Console\Command;

class HistoryPruneCommand extends Command
{
    protected $signature = 'history:prune';

    protected $description = 'Remove the history lines that are older than the keep time (Settings > History).';

    public function handle(): int
    {
        $this->info('Removed '.HistoryKeeper::prune().' old history line(s) (older than '.HistoryKeeper::months().' months).');

        return self::SUCCESS;
    }
}
