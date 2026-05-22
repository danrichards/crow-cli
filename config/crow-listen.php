<?php

return [
    'api_url' => env('CROW_API_URL', 'https://crow.test/api/v1'),
    'api_token' => env('CROW_API_TOKEN'),
    'app_id' => env('CROW_APP_ID'),
    'public_url' => env('CROW_LISTEN_PUBLIC_URL'),

    'host' => env('CROW_LISTEN_HOST', '127.0.0.1'),
    'port' => (int) env('CROW_LISTEN_PORT', 8787),
    'listener_secret' => env('CROW_LISTEN_SECRET'),
];
