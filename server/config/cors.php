<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:3000'),
        // Also allow the 127.0.0.1 equivalent so browsers that resolve
        // 'localhost' as 127.0.0.1 don't get a CORS rejection.
        str_replace('localhost', '127.0.0.1', env('FRONTEND_URL', 'http://localhost:3000')),
    ]),

    'allowed_origins_patterns' => ['#^http://([a-z0-9-]+\.)?localhost:3000$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
