<?php

return [
    'default' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | Keys are ISO language codes. Layout direction is a separate company
    | setting (`rtl`), not tied to language.
    |
    */
    'supported' => [
        'en' => [
            'label' => 'English',
            'native' => 'English',
        ],
        'ur' => [
            'label' => 'Urdu',
            'native' => 'اردو',
        ],
    ],
];
