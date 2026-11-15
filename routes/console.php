<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A backup every night. On the computer or server a scheduled task must run "php artisan schedule:run" every minute (see Settings > Backups).
\Illuminate\Support\Facades\Schedule::command('backup:run')->dailyAt('03:00')->withoutOverlapping();
