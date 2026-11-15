<?php

namespace App\Support;

use App\Models\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The languages of the site, from the Languages screen. While that table is not there (or is empty) the old list in
 * config/infofluxes.php is used, so the site always has at least its main language.
 */
class Languages
{
    private const CACHE_KEY = 'languages_v1';

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Every language, in order.
     *
     * @return list<array{code: string, name: string, native_name: string, flag: string, is_rtl: bool, status: string, is_default: bool}>
     */
    public static function all(): array
    {
        $rows = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('languages')) {
                return [];
            }

            return Language::query()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (Language $l) => ['code' => $l->code, 'name' => $l->name, 'native_name' => $l->native_name, 'flag' => $l->flag, 'is_rtl' => (bool) $l->is_rtl, 'status' => $l->status, 'is_default' => (bool) $l->is_default])->all();
        });

        return $rows !== [] ? $rows : static::fromConfig();
    }

    /** The list of config/infofluxes.php, in the shape of the table. */
    private static function fromConfig(): array
    {
        $main = (string) config('infofluxes.default_locale', 'en');
        $rtl = (array) config('infofluxes.rtl_locales', []);
        $out = [];
        foreach ((array) config('infofluxes.locales', ['en' => 'English']) as $code => $name) {
            $out[] = ['code' => $code, 'name' => $name, 'native_name' => $name, 'flag' => $code === 'en' ? 'gb' : 'un', 'is_rtl' => in_array($code, $rtl, true), 'status' => 'active', 'is_default' => $code === $main];
        }

        return $out;
    }

    /** The languages a visitor can use: code => name in its own language. @return array<string, string> */
    public static function active(): array
    {
        $out = [];
        foreach (static::all() as $l) {
            if ($l['status'] === 'active') {
                $out[$l['code']] = $l['native_name'];
            }
        }

        return $out ?: [(string) config('infofluxes.default_locale', 'en') => 'English'];
    }

    /** The languages whose texts an editor fills in: the ones that are on and the ones coming soon (so texts can be ready before the launch). @return array<string, string> */
    public static function forEditors(): array
    {
        $out = [];
        foreach (static::all() as $l) {
            if (in_array($l['status'], ['active', 'coming_soon'], true)) {
                $out[$l['code']] = $l['name'];
            }
        }

        return $out ?: static::active();
    }

    /** What the language menu shows: the languages that are on, and those coming soon. @return list<array> */
    public static function menu(): array
    {
        $rows = array_values(array_filter(static::all(), fn ($l) => in_array($l['status'], ['active', 'coming_soon'], true)));

        return $rows ?: static::fromConfig();
    }

    public static function isRtl(string $code): bool
    {
        foreach (static::all() as $l) {
            if ($l['code'] === $code) {
                return $l['is_rtl'];
            }
        }

        return in_array($code, (array) config('infofluxes.rtl_locales', []), true);
    }

    public static function flagUrl(string $flag): string
    {
        return asset('flags/'.(is_file(public_path('flags/'.$flag.'.svg')) ? $flag : 'un').'.svg');
    }

    /** @return array{flags: array<string, string>, catalog: array<string, array>} */
    public static function data(): array
    {
        return (array) require resource_path('data/languages.php');
    }
}
