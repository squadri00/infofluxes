<?php

namespace App\Console\Commands;

use App\Support\ImageStore;
use App\Support\PictureOptimizer;
use Illuminate\Console\Command;

/** Makes the pictures that are already on the site light (WebP, at most the set size). Shows what it would do; "--apply" does it. */
class OptimizePicturesCommand extends Command
{
    protected $signature = 'pictures:optimize {--apply : really change the pictures (without it, nothing is changed)}';

    protected $description = 'Save the pictures already on the site as light WebP files (the originals are kept in a private folder)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('Limit: '.round(ImageStore::maxBytes() / 1024).' KB, no wider than '.ImageStore::maxWidth().' pixels.'.($apply ? '' : '  (a trial: nothing is changed)'));

        $result = PictureOptimizer::run($apply);

        $this->table(['Picture', 'Before', 'After', 'What'], array_map(fn ($f) => [
            $f['path'], round($f['before'] / 1024).' KB', $f['after'] === null ? '-' : round($f['after'] / 1024).' KB', $f['status'],
        ], $result['files']));

        $this->info(($apply ? 'Saved ' : 'Would save ').round($result['saved'] / 1048576, 1).' MB in all.');
        if ($result['unused'] !== []) {
            $this->line(count($result['unused']).' pictures are used by nothing and were left alone: '.implode(', ', $result['unused']));
        }
        if (! $apply && collect($result['files'])->contains('status', 'would change')) {
            $this->warn('Run it again with --apply to do it. Take a database backup first.');
        }

        return self::SUCCESS;
    }
}
