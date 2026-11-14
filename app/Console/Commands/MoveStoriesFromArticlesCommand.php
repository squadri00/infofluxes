<?php

namespace App\Console\Commands;

use App\Support\StoryMover;
use Illuminate\Console\Command;

/** Moves the picture-story articles into Visual Stories. Shows what it would do; "--apply" does it. */
class MoveStoriesFromArticlesCommand extends Command
{
    protected $signature = 'stories:move-from-articles {--apply : really move them (without it, nothing is changed)}';

    protected $description = 'Move the articles that have chapters and slides into Visual Stories (the articles go to the Trash)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $result = StoryMover::run($apply);

        $this->table(['Article', 'Story', 'Title', 'Chapters', 'Slides', 'What'], array_map(fn ($r) => [
            $r['article'], $r['story'] ?? '-', mb_substr($r['title'], 0, 50), $r['chapters'], $r['slides'], $r['status'],
        ], $result['stories']));

        $this->info(($apply ? 'Moved ' : 'Would move ').count($result['stories']).' stories.');
        if (! $apply && $result['stories'] !== []) {
            $this->warn('Run it again with --apply to do it. Take a database backup first.');
        }

        return self::SUCCESS;
    }
}
