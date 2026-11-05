<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. Set BOT_NOISE_ENABLED=false to see everything again, for
    | example while you look into a wave of traffic.
    |
    */

    'enabled' => env('BOT_NOISE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | The exception filter only runs in these environments, so a real bug still
    | shows up on staging and during development. The array guard and the query
    | string cleanup prevent an error instead of hiding one and run everywhere.
    |
    */

    'environments' => ['production'],

    /*
    |--------------------------------------------------------------------------
    | Sanitize query string
    |--------------------------------------------------------------------------
    |
    | Replace invalid UTF-8 in the query string. Those bytes end up in a Livewire
    | snapshot or a JSON response and crash the request on json_encode.
    |
    */

    'sanitize_query_string' => true,

    /*
    |--------------------------------------------------------------------------
    | Array guard
    |--------------------------------------------------------------------------
    |
    | Answer 403 when a Livewire update sends an array to a top-level property
    | that does not hold or accept one.
    |
    */

    'array_guard' => true,

    /*
    |--------------------------------------------------------------------------
    | Array guard exceptions
    |--------------------------------------------------------------------------
    |
    | Properties that may receive an array anyway, as 'component.property'. For
    | example 'contact-form.recipients'. Typing the property as array is better.
    |
    */

    'array_guard_except' => [],

];
