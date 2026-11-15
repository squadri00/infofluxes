<?php

namespace App\Console\Commands;

use App\Support\OverviewMaker;
use Illuminate\Console\Command;

class MakeOverviewCategoriesCommand extends Command
{
    protected $signature = 'categories:make-overviews {--apply : really do it (without this nothing is changed)} {--skip=* : ids of headings to leave alone (C-540 or 540)}';

    protected $description = 'Give each heading that holds articles an Overview sub-category and move its articles there (articles sit only in last-level categories).';

    public function handle(): int
    {
        $r = OverviewMaker::run((bool) $this->option('apply'), array_map(fn ($v) => (int) preg_replace('/\D/', '', (string) $v), (array) $this->option('skip')));

        if ($r['headings'] === []) {
            $this->info('No heading holds articles. Nothing to do.');
        } else {
            $this->table(['Heading', 'Name', 'Articles', 'Extra links', 'Overview'], array_map(fn ($h) => ['C-'.$h['id'], $h['name'], $h['main'], $h['extra'], $h['overview'] ? 'C-'.$h['overview'] : '-'], $r['headings']));
            $this->info($r['applied'] ? 'Done.' : 'Nothing was changed. Run it again with --apply to do it. Take a database backup first.');
        }
        $left = OverviewMaker::offenders();
        if ($left->isNotEmpty()) {
            $this->warn($left->count().' article(s) still sit in a category with sub-categories.');
        }

        return self::SUCCESS;
    }
}
