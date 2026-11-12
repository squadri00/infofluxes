<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\OldSiteImport;
use Illuminate\Console\Command;

class MoveFromOldSiteCommand extends Command
{
    protected $signature = 'site:move-from-old {--check : Only show what would be made, save nothing}';

    protected $description = 'Move the categories and subject indexes of the old InfoFluxes into this site';

    public function handle(): int
    {
        if (! OldSiteImport::available()) {
            $this->error('The old database cannot be read. Set OLD_SITE_DATABASE in .env.');

            return self::FAILURE;
        }
        $import = new OldSiteImport;
        $result = $this->option('check') ? $import->check() : $import->move(User::where('role', 'superuser')->where('status', 'active')->orderBy('id')->first());

        $this->table(['Index', 'New categories', 'Already here', 'New articles', 'Already here', 'With text'], collect($result['groups'])->map(fn ($g, $k) => [$k, $g['categories_new'], $g['categories_existing'], $g['articles_new'] ?? 0, $g['articles_existing'] ?? 0, $g['with_text'] ?? 0])->values()->all());
        $t = $result['total'];
        $this->info(($this->option('check') ? 'Would make' : 'Made').": {$t['categories_new']} categories and {$t['articles_new']} draft articles. Already here: {$t['categories_existing']} categories, {$t['articles_existing']} articles.");

        return self::SUCCESS;
    }
}
