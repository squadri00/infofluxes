<?php

namespace App\Support;

use App\Models\Clip;

/**
 * The clips in the open drop-down menus: up to three random clips for each menu (one big clip in the compact menus), a new pick on every page load.
 * Every menu on the page gets other clips than the one before it, as far as there are enough clips.
 */
class MenuClips
{
    public const PER_MENU = 3;

    /** @var list<Clip>|null the clips in a random order, for this page load */
    private static ?array $pool = null;

    private static int $taken = 0;

    public static function forget(): void
    {
        static::$pool = null;
        static::$taken = 0;
    }

    /** @return list<array{id: int, title: string, blurb: string, src: string, poster: ?string, url: string, label: string}> */
    public static function next(int $count = self::PER_MENU): array
    {
        if (! SiteControls::on('clips_page')) {
            return [];
        }
        static::$pool ??= Clip::playable()->get()->shuffle()->values()->all();
        $n = count(static::$pool);
        if ($n === 0) {
            return [];
        }
        $out = [];
        $want = min($count, $n);
        for ($i = 0; $i < $n && count($out) < $want; $i++) {
            $clip = static::$pool[(static::$taken + $i) % $n];
            if ($clip->exists()) {
                $p = $clip->forPlayer();
                $p['url'] = $p['url'] !== '' ? $p['url'] : route('clips');
                $out[] = $p;
            }
        }
        static::$taken += $count;

        return $out;
    }
}
