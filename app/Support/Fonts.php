<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The fonts of the site. The super user picks them in Site settings > Fonts.
 *
 * Two kinds of fonts: "device" fonts (already on the visitor's phone or computer, no file needed) and
 * "file" fonts (a .woff2 in public/fonts/site, served from this site, never from another company).
 * A file font only shows in the lists once its file is really there. Nothing chosen = the usual look.
 */
class Fonts
{
    /** Where the font files live, inside public/. */
    public const DIR = 'fonts/site';

    /** Text size steps: percent of the usual size. */
    public const SIZES = ['small' => 94, 'normal' => 100, 'large' => 108, 'larger' => 116];

    public const SIZE_LABELS = ['small' => 'Small', 'normal' => 'Normal (usual)', 'large' => 'Large', 'larger' => 'Larger'];

    /**
     * Fonts for headings and body text. key => [label, family name, fallback stack, file or null, weights].
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: ?string, 4: string}>
     */
    public static function catalog(): array
    {
        $sans = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
        $serif = 'Georgia, "Times New Roman", serif';

        return [
            'system' => ['System default (usual)', '', $sans, null, ''],
            'segoe' => ['Segoe UI / Helvetica (device)', '', '"Segoe UI", "Helvetica Neue", Helvetica, Arial, sans-serif', null, ''],
            'verdana' => ['Verdana (device, very easy to read)', '', 'Verdana, Geneva, Tahoma, sans-serif', null, ''],
            'trebuchet' => ['Trebuchet (device)', '', '"Trebuchet MS", "Segoe UI", Arial, sans-serif', null, ''],
            'georgia' => ['Georgia (device, serif)', '', $serif, null, ''],
            'palatino' => ['Palatino (device, serif)', '', '"Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif', null, ''],
            'times' => ['Times (device, serif)', '', '"Times New Roman", Times, serif', null, ''],
            'inter' => ['Inter', 'Inter', $sans, 'inter.woff2', '100 900'],
            'open-sans' => ['Open Sans', 'Open Sans', $sans, 'open-sans.woff2', '300 800'],
            'lato' => ['Lato', 'Lato', $sans, 'lato-400.woff2', '400'],
            'noto-sans' => ['Noto Sans', 'Noto Sans', $sans, 'noto-sans.woff2', '100 900'],
            'poppins' => ['Poppins', 'Poppins', $sans, 'poppins-400.woff2', '400'],
            'merriweather' => ['Merriweather (serif)', 'Merriweather', $serif, 'merriweather.woff2', '300 900'],
            'source-serif' => ['Source Serif (serif)', 'Source Serif 4', $serif, 'source-serif.woff2', '200 900'],
            'playfair' => ['Playfair Display (serif, for headings)', 'Playfair Display', $serif, 'playfair-display.woff2', '400 900'],
        ];
    }

    /** The catalog without file fonts whose file is missing. @return array<string, array> */
    public static function available(): array
    {
        return array_filter(static::catalog(), fn ($f) => $f[3] === null || is_file(public_path(static::DIR.'/'.$f[3])));
    }

    /** @return array<string, string> key => label, for a dropdown */
    public static function options(): array
    {
        return array_map(fn ($f) => $f[0], static::available());
    }

    /**
     * Fonts for Arabic text (the Quran lines, the Arabic quiz options and so on).
     *
     * @return array<string, array{0: string, 1: string, 2: string}> key => [label, family list, file or '']
     */
    public static function arabic(): array
    {
        $all = [
            'amiri' => ['Amiri (usual, classic Naskh)', '"Amiri", "Noto Naskh Arabic", "Scheherazade New", "Traditional Arabic", "Arabic Typesetting", "Segoe UI", Tahoma, serif', ''],
            'device' => ['The visitor\'s own Arabic font (device)', '"Traditional Arabic", "Arabic Typesetting", "Segoe UI", Tahoma, serif', ''],
            'naskh' => ['Noto Naskh Arabic', '"Noto Naskh Arabic", "Amiri", "Traditional Arabic", "Segoe UI", Tahoma, serif', 'noto-naskh-arabic.woff2'],
            'scheherazade' => ['Scheherazade New', '"Scheherazade New", "Amiri", "Traditional Arabic", "Segoe UI", Tahoma, serif', 'scheherazade-new.woff2'],
        ];

        return array_filter($all, fn ($f) => $f[2] === '' || is_file(public_path(static::DIR.'/'.$f[2])));
    }

    /** @return array<string, string> */
    public static function arabicOptions(): array
    {
        return array_map(fn ($f) => $f[0], static::arabic());
    }

    /** The chosen key, or the usual one when nothing (or something no longer available) is chosen. */
    public static function chosen(string $setting, array $allowed, string $usual): string
    {
        $v = (string) Setting::get($setting);

        return array_key_exists($v, $allowed) ? $v : $usual;
    }

    /** The font stack for a key of the main catalog. */
    public static function stack(string $key): string
    {
        $f = static::catalog()[$key] ?? static::catalog()['system'];

        return $f[1] !== '' ? '"'.$f[1].'", '.$f[2] : $f[2];
    }

    /** @@font-face for a file font, or an empty string for a device font. */
    private static function face(string $family, string $file, string $weights): string
    {
        if ($family === '' || $file === '' || ! is_file(public_path(static::DIR.'/'.$file))) {
            return '';
        }
        $url = asset(static::DIR.'/'.$file).'?v='.(@filemtime(public_path(static::DIR.'/'.$file)) ?: 1);

        return '@font-face{font-family:"'.$family.'";font-style:normal;font-weight:'.$weights.';font-display:swap;src:url("'.$url.'") format("woff2")}';
    }

    /** A style block for the page head, or an empty string while everything is on the usual values. */
    public static function css(): string
    {
        $catalog = static::available();
        $heading = static::chosen('font_heading', $catalog, 'system');
        $body = static::chosen('font_body', $catalog, 'system');
        $arabic = static::chosen('font_arabic', static::arabic(), 'amiri');
        $size = static::chosen('font_size', static::SIZES, 'normal');

        $css = '';
        $vars = [];
        if ($body !== 'system') {
            $f = $catalog[$body];
            $css .= static::face($f[1], (string) $f[3], $f[4]);
            $vars[] = '--font:'.static::stack($body);
        }
        if ($heading !== 'system' && $heading !== $body) {
            $f = $catalog[$heading];
            $css .= static::face($f[1], (string) $f[3], $f[4]);
        }
        if ($heading !== 'system') {
            $vars[] = '--font-heading:'.static::stack($heading);
            $css .= 'h1,h2,h3,h4,h5,h6,.site-header__name{font-family:var(--font-heading)}';
        }
        if ($arabic !== 'amiri') {
            $a = static::arabic()[$arabic];
            $css .= static::face(trim(explode(',', $a[1])[0], '" '), $a[2], '400 700');
            $vars[] = '--font-arabic:'.$a[1];
        }
        if ($size !== 'normal') {
            $css .= 'html{font-size:'.static::SIZES[$size].'%}';
        }
        if ($vars) {
            $css = ':root{'.implode(';', $vars).'}'.$css;
        }

        return $css === '' ? '' : '<style>'.$css.'</style>';
    }

    /** The same fonts as a small preview for the admin screen. */
    public static function preview(?string $heading, ?string $body, ?string $arabic): string
    {
        $catalog = static::available();
        $h = array_key_exists((string) $heading, $catalog) ? $heading : 'system';
        $b = array_key_exists((string) $body, $catalog) ? $body : 'system';
        $a = static::arabic()[array_key_exists((string) $arabic, static::arabic()) ? $arabic : 'amiri'];

        $faces = '';
        foreach ([$h, $b] as $k) {
            $f = $catalog[$k];
            $faces .= static::face($f[1], (string) $f[3], $f[4]);
        }
        $faces .= static::face(trim(explode(',', $a[1])[0], '" '), $a[2], '400 700');

        $q = fn (string $stack) => str_replace('"', "'", $stack);

        return '<style>'.$faces.'</style>'
            .'<div style="border:1px solid #8884;border-radius:10px;padding:16px 20px">'
            .'<div style="font:700 1.5rem/1.3 '.$q(static::stack($h)).'">Every belief has a source</div>'
            .'<p style="margin:.5rem 0 0;font:1rem/1.6 '.$q(static::stack($b)).'">This is how the body text will look: their logic, your judgment. Read it for a moment, then decide.</p>'
            .'<p dir="rtl" style="margin:.7rem 0 0;font:1.5rem/2 '.$q($a[1]).'">بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ</p>'
            .'</div>';
    }
}
