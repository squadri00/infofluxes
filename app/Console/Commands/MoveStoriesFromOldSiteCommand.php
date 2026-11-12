<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\User;
use App\Support\OldStoryImport;
use Illuminate\Console\Command;

class MoveStoriesFromOldSiteCommand extends Command
{
    protected $signature = 'site:move-stories-from-old {--check : Only show what would be made, save nothing} {--category= : The code or id of the category for the stories (default: About Islam)}';

    protected $description = 'Move the old slide stories (with their pictures) into this site as articles with story slides';

    public function handle(): int
    {
        if (! OldStoryImport::available()) {
            $this->error('The old site cannot be read. Set OLD_SITE_DATABASE and OLD_SITE_PATH in .env.');

            return self::FAILURE;
        }
        $wanted = (string) $this->option('category');
        $category = $wanted !== ''
            ? Category::find((int) preg_replace('/\D/', '', $wanted))
            : Category::whereNull('parent_id')->get()->first(fn ($c) => $c->mainName() === 'About Islam');
        if (! $category) {
            $this->error('The category was not found. Use --category=C-1 (the code of a category).');

            return self::FAILURE;
        }

        $import = new OldStoryImport($category->id);
        $result = $this->option('check') ? $import->check() : $import->move(User::where('role', 'superuser')->where('status', 'active')->orderBy('id')->first());
        $t = $result['total'];

        $this->line('Category: '.$category->mainName().' ('.$category->code.')');
        $this->info(($this->option('check') ? 'Would move' : 'Moved').": {$t['stories_new']} stories, {$t['chapters']} chapters, {$t['slides']} slides. Pictures ".($this->option('check') ? 'to copy' : 'copied').": {$t['images_copied']} (missing: {$t['images_missing']}). Published {$t['published']}, drafts {$t['drafts']}.");
        $this->line("Already moved before: {$t['stories_existing']}. Left alone (same title): {$t['stories_same_title']}".($result['same_title'] ? ' - '.implode('; ', $result['same_title']) : ''));

        return self::SUCCESS;
    }
}