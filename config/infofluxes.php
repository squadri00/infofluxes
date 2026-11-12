<?php

/**
 * Site-wide choices that are not secrets.
 * Languages: English is the main one. To add a language later, add it here (for example 'ar' => 'Arabic')
 * and the article and category screens show a tab for it. No other change is needed.
 */
return [
    'default_locale' => 'en',

    // code => name shown. Only English is switched on at the start.
    'locales' => [
        'en' => 'English',
    ],

    // Languages that read right to left (used when one of them is switched on).
    'rtl_locales' => ['ar', 'ur', 'fa', 'he'],
];
