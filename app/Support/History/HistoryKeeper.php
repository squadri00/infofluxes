<?php

namespace App\Support\History;

use App\Models\Activity;
use App\Models\Setting;

/** How long the history is kept, and the tidying of old lines. */
class HistoryKeeper
{
    public const DEFAULT_MONTHS = 12;

    public static function months(): int
    {
        return max(1, min(120, (int) Setting::get('history_keep_months', self::DEFAULT_MONTHS)));
    }

    /** Removes the lines older than the keep time. @return int how many */
    public static function prune(?int $months = null): int
    {
        return Activity::query()->where('created_at', '<', now()->subMonths($months ?? static::months()))->delete();
    }
}
