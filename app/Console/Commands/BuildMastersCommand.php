<?php

namespace App\Console\Commands;

use App\Support\MasterBuilder;
use Illuminate\Console\Command;

class BuildMastersCommand extends Command
{
    protected $signature = 'masters:build {--apply : really do it (without it only counts)} {--csv= : also write the list of subjects to this CSV file} {--plan=database/data/about-islam-masters.php : the plan file}';

    protected $description = 'Add the masters, topics and planned subjects of a plan file under a section (About Islam). Safe to run again.';

    public function handle(): int
    {
        $file = base_path((string) $this->option('plan'));
        if (! is_file($file)) {
            $this->error('The plan file was not found: '.$file);

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $r = MasterBuilder::run(require $file, $apply);

        $this->line(($apply ? 'Done' : 'Dry run (nothing changed)').':');
        $this->line('  new masters: '.$r['masters'].', new topics: '.$r['topics'].', new planned subjects: '.$r['subjects']);
        $this->line('  already there: '.$r['existing'].', same title as an existing article (listed in both places): '.$r['linked'].', close to an existing one (please look): '.$r['overlaps']);
        if ($path = $this->option('csv')) {
            $h = fopen($path, 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Master', 'Topic', 'Subtopic', 'Subject', 'What happened', 'Note']);
            foreach ($r['rows'] as $row) {
                fputcsv($h, [$row['master'], $row['topic'], $row['subtopic'], $row['title'], $row['status'], $row['note']]);
            }
            fclose($h);
            $this->line('  list written to '.$path);
        }
        if (! $apply) {
            $this->line('Make a backup (php artisan backup:run), then run again with --apply.');
        }

        return self::SUCCESS;
    }
}
