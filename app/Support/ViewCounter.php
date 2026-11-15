<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Counts a page view of an article or a story: once per visitor per visit, never for staff, robots or preview of drafts. */
class ViewCounter
{
    public static function count(Model $item, ?Request $request = null): bool
    {
        $request ??= request();
        if (! $request->isMethod('GET') || static::isRobot((string) $request->userAgent()) || static::isStaff()) {
            return false;
        }
        $key = $item->getMorphClass().':'.$item->getKey();
        $seen = (array) $request->session()->get('viewed', []);
        if (in_array($key, $seen, true)) {
            return false;
        }
        $request->session()->put('viewed', array_slice([...$seen, $key], -200));
        // Straight to the table: a view is not an edit, so no "changed" date and no History line.
        $item->newQuery()->whereKey($item->getKey())->toBase()->increment('views');
        $item->views = (int) $item->views + 1;

        return true;
    }

    public static function isRobot(string $agent): bool
    {
        return $agent === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|curl|wget|python|httpclient/i', $agent);
    }

    private static function isStaff(): bool
    {
        return auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    /** 1,234 -> "1.2K" for the small number beside the eye. */
    public static function short(int $n): string
    {
        if ($n >= 1000000) {
            return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.').'M';
        }
        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.').'K';
        }

        return (string) $n;
    }
}
