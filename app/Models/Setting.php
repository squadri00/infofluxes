<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One setting per row. A setting is either a plain value ("logo") or a text per language ({"en": "..."}).
 * Nothing about the site's words is written in code: they are here, and the code only has a plain default.
 */
class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array', 'custom_data' => 'array'];
    }

    /** Settings that hold a text for each language. */
    public const TRANSLATED = ['site_name', 'tagline', 'footer_about', 'copyright', 'home_headline', 'home_intro', 'home_message'];

    private static ?array $cache = null;

    public static function all_(): array
    {
        return static::$cache ??= static::query()->pluck('value', 'key')->all();
    }

    public static function forget(): void
    {
        static::$cache = null;
    }

    /** A plain setting, or the text in the visitor's language (falling back to English, then to the default). */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::all_()[$key] ?? null;
        if ($value === null || $value === '' || $value === []) {
            return $default;
        }
        if (in_array($key, static::TRANSLATED, true) && is_array($value)) {
            $text = $value[app()->getLocale()] ?? $value[config('infofluxes.default_locale')] ?? null;

            return filled($text) ? $text : $default;
        }

        return $value;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::forget();
    }
}
