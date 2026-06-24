<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Proyek ini memakai apiPrefix '' (route tanpa /api), jadi 'paths'
    | diperluas ke '*' agar semua route mendapat header CORS. Tanpa ini
    | request lintas-origin dari Swagger UI gagal ("Failed to fetch").
    |
    */

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
