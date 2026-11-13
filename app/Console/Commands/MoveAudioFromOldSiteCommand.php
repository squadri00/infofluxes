<?php

namespace App\Console\Commands;

use App\Support\OldStoryImport;
use Illuminate\Console\Command;

class MoveAudioFromOldSiteCommand extends Command
{
    protected $signature = 'site:move-audio-from-old {--check : Only show what would be copied, save nothing}';

    protected $description = 'Copy the audio files of the old slide stories into the matching slides here (they stay Draft until a person approves them)';

    public function handle(): int
    {
        if (! OldStoryImport::available()) {
            $this->error('The old site cannot be read. Set OLD_SITE_DATABASE and OLD_SITE_PATH in .env.');

            return self::FAILURE;
        }
        $import = new OldStoryImport;
        $r = $this->option('check') ? $import->checkAudio() : $import->moveAudio();
        $t = $r['total'];

        $this->info(($this->option('check') ? 'Would copy' : 'Copied').": {$t['audio_copied']} audio files (missing in the old site: {$t['audio_missing']}).");
        $this->line("Stories matched: {$t['stories_matched']} (not moved yet: {$t['stories_not_moved']}). Slides matched: {$t['slides_matched']}, not matched: {$t['slides_unmatched']}.");

        return self::SUCCESS;
    }
}