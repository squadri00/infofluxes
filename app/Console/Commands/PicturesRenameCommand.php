<?php

namespace App\Console\Commands;

use App\Support\PictureRenamer;
use Illuminate\Console\Command;

/** Names the pictures already on the site after their pages (for search engines). Without --apply it only shows what it would do. */
class PicturesRenameCommand extends Command
{
    protected $signature = 'pictures:rename {--apply : really rename the files (without it nothing changes)} {--csv= : also write the list old name, new name, page to this CSV file}';

    protected $description = 'Give every picture a file name made from its page; the old addresses keep working (they go to the new ones)';

    public function handle(): int
    {
        $plan = PictureRenamer::plan();
        if ($plan === []) {
            $this->info('Every picture already has a name made from its page. Nothing to do.');

            return self::SUCCESS;
        }

        $this->table(['Old name', 'New name', 'Page'], array_map(fn ($r) => [$r['old'], $r['new'], mb_substr($r['subject'], 0, 50)], array_slice($plan, 0, 25)));
        $this->line(count($plan).' picture(s) would get a new name'.(count($plan) > 25 ? ' (25 are shown)' : '').'.');

        if ($csv = $this->option('csv')) {
            $fh = fopen($csv, 'w');
            fputcsv($fh, ['old', 'new', 'page'], ',', '"', '');
            foreach ($plan as $r) {
                fputcsv($fh, [$r['old'], $r['new'], $r['subject']], ',', '"', '');
            }
            fclose($fh);
            $this->line('The list is in '.$csv);
        }
        if (! $this->option('apply')) {
            $this->warn('Dry run: nothing was changed. Take a backup (php artisan backup:run), then run again with --apply.');

            return self::SUCCESS;
        }

        $result = PictureRenamer::apply($plan);
        $this->info($result['renamed'].' picture(s) renamed. The old addresses go to the new ones.');
        foreach ($result['problems'] as $problem) {
            $this->error($problem);
        }

        return $result['problems'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
