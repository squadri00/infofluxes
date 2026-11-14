<?php

/**
 * Site-wide choices that are not secrets.
 * Languages: English is the main one. To add a language later, add it here (for example 'ar' => 'Arabic')
 * and the article and category screens show a tab for it. No other change is needed.
 */
return [
    'default_locale' => 'en',

    // Where the old InfoFluxes files are, only on the computer that still has them (used by "Move from the old site"). Empty on the live site.
    'old_site_path' => env('OLD_SITE_PATH'),

    // How easy a written article must be to read (used by the AI import check). Average words in a sentence, and the longest sentence allowed.
    'reading' => ['avg_max' => 18, 'sentence_max' => 35],

    // code => name shown. Only English is switched on at the start.
    'locales' => [
        'en' => 'English',
    ],

    // Languages that read right to left (used when one of them is switched on).
    'rtl_locales' => ['ar', 'ur', 'fa', 'he'],
];
