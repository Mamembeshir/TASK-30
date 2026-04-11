<?php

return [
    'name'     => env('APP_NAME', 'MedVoyage'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'url'      => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale'   => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale'    => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher'   => 'AES-256-CBC',
    'key'      => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    ],
    'maintenance' => ['driver' => 'file'],

    // Facility settings
    'facility_timezone' => env('FACILITY_TIMEZONE', 'America/New_York'),
    'facility_name'     => env('FACILITY_NAME', 'MedVoyage Medical Center'),

    // Business rules
    'seat_hold_minutes'       => (int) env('SEAT_HOLD_MINUTES', 10),
    'waitlist_offer_minutes'  => (int) env('WAITLIST_OFFER_MINUTES', 10),
];
