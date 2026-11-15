<?php

namespace App\Console\Commands;

use App\Support\IndexMerger;
use Illuminate\Console\Command;

class MergeIntoIndexEntryCommand extends Command
{
    protected $signature = 'index:merge {article : the article to move (4026 or A-4026)} {entry : the blank index entry (AL-2.1.001 or 723 or A-723)} {--apply : really do it (without this nothing is changed)}';

    protected $description = 'Move an article into the blank index entry about the same subject. The article goes to the Trash.';

    public function handle(): int
    {
        $from = IndexMerger::find((string) $this->argument('article'));
        $entry = IndexMerger::find((string) $this->argument('entry'));
        if (! $from || ! $entry) {
            $this->error('One of the two could not be found.');

            return self::FAILURE;
        }

        $r = IndexMerger::run($from, $entry, (bool) $this->option('apply'));
        if (! $r['ok']) {
            $this->error($r['problem']);

            return self::FAILURE;
        }

        $this->line('From: '.$r['from']);
        $this->line('Into: '.$r['into']);
        foreach ($r['moves'] as $what => $n) {
            $this->line('  '.$what.': '.$n);
        }
        $this->info($r['applied'] ? 'Done. The article is in the Trash and its code leads to the index entry.' : 'Nothing was changed. Run it again with --apply to do it. Take a database backup first.');

        return self::SUCCESS;
    }
}
