<?php

namespace App\Console\Commands;

use App\Support\LinkSweep;
use Illuminate\Console\Command;

class LinksCheckCommand extends Command
{
    protected $signature = 'links:check {--limit=60 : the most links to check in this run} {--seconds=50 : stop after this many seconds} {--all : check every link, not only the ones that are due}';

    protected $description = 'Check the source links of all articles (those not checked for a week, or never). A broken link never hides an article.';

    public function handle(): int
    {
        if ($this->option('all')) {
            LinkSweep::sync();
            $this->line('Queued '.LinkSweep::queueAll().' link(s).');
        }
        $checked = LinkSweep::runDue(max(1, (int) $this->option('limit')), max(5, (int) $this->option('seconds')));
        $p = LinkSweep::progress();
        $this->info('Checked '.$checked.' link(s). '.$p['queued'].' still waiting in the queue, '.$p['total'].' links in all.');

        return self::SUCCESS;
    }
}
