<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| The SPA runs on a different origin than the API during local development,
| so it needs credentialed CORS. "supports_credentials" must stay true and
| "allowed_origins" must list explicit origins (a wildcard is rejected by
| browsers once credentials are involved).
|
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(
        array_map('trim', explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173')))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
