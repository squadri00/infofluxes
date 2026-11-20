<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Support\CategoryFlattener;
use Illuminate\Console\Command;

class FlattenCategoriesCommand extends Command
{
    protected $signature = 'categories:flatten {slug : the slug of the section, for example about-islam} {--levels=3 : how many levels to keep, the section itself is level 1} {--apply : really do it (without it only counts)}';

    protected $description = 'Shrink the category tree of one section to a few levels: deeper articles move up and keep their old names as a Subtopic.';

    public function handle(): int
    {
        $root = Category::query()->where('slug', $this->argument('slug'))->first();
        if (! $root) {
            $this->error('No category with that slug.');

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $r = CategoryFlattener::run($root, (int) $this->option('levels'), $apply);

        $this->line(($apply ? 'Done' : 'Dry run (nothing changed)').' for "'.$root->mainName().'", keeping '.(int) $this->option('levels').' levels:');
        $this->line('  categories kept: '.$r['kept'].', categories moved to the Trash: '.$r['removed']);
        $this->line('  articles moved up: '.$r['articles'].', stories: '.$r['stories'].', facts: '.$r['facts'].', story runs: '.$r['runs'].', "also in" links: '.$r['extra_links']);
        if (! $apply) {
            $this->line('Make a backup (php artisan backup:run), then run again with --apply.');
        }

        return self::SUCCESS;
    }
}
