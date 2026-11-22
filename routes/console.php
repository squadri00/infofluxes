<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A backup every night. On the computer or server a scheduled task must run "php artisan schedule:run" every minute (see Settings > Backups).
\Illuminate\Support\Facades\Schedule::command('backup:run')->dailyAt('03:00')->withoutOverlapping();

// Old history lines are tidied once a week (Settings > History says how long they are kept).
\Illuminate\Support\Facades\Schedule::command('history:prune')->weeklyOn(0, '04:00');

// The weekly news email for the members who follow things (Monday morning). Needs the mail settings (MAIL_*) in the .env file.
\Illuminate\Support\Facades\Schedule::command('members:digest')->weeklyOn(1, '08:00')->withoutOverlapping();

// Source links are checked a few at a time through the day; each link is looked at again after a week.
\Illuminate\Support\Facades\Schedule::command('links:check --limit=60')->hourly()->withoutOverlapping();
