<?php

namespace App\Support;

use App\Models\Setting;

/** How the pictures on cards (categories, articles, stories) are fitted and coloured. The super user sets it in Site settings. */
class CardPictures
{
    public const FITS = ['cover' => 'Fill the box (the edges are cut a little)', 'contain' => 'Show the whole picture (empty bands may show)'];

    public const FOCUS = ['top' => 'Top (keeps heads and skies)', 'center' => 'Middle', 'bottom' => 'Bottom'];

    /** A small style block for the page head, or an empty string while everything is on the usual values. */
    public static function css(): string
    {
        $fit = array_key_exists((string) Setting::get('card_image_fit'), static::FITS) ? Setting::get('card_image_fit') : 'cover';
        $focus = array_key_exists((string) Setting::get('card_image_focus'), static::FOCUS) ? Setting::get('card_image_focus') : 'center';
        $sat = static::percent('card_image_saturation', 30, 130);
        $con = static::percent('card_image_contrast', 70, 120);
        $bri = static::percent('card_image_brightness', 70, 130);

        $vars = [];
        if ($fit !== 'cover') {
            $vars[] = "--card-fit:{$fit}";
        }
        if ($focus !== 'center') {
            $vars[] = "--card-pos:center {$focus}";
        }
        if ($sat !== 100 || $con !== 100 || $bri !== 100) {
            $vars[] = "--card-filter:saturate({$sat}%) contrast({$con}%) brightness({$bri}%)";
        }

        return $vars ? '<style>:root{'.implode(';', $vars).'}</style>' : '';
    }

    private static function percent(string $key, int $min, int $max): int
    {
        $v = Setting::get($key);

        return is_numeric($v) ? max($min, min($max, (int) $v)) : 100;
    }
}
