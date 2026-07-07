<?php

return [
    'default_api_url' => 'https://crow.test/api/v1',
    'config_path' => env('CROW_CONFIG_PATH'),
    'global_config_path' => env('CROW_GLOBAL_CONFIG_PATH'),
    'project_path' => null,

    'api_url' => env('CROW_API_URL'),
    'api_token' => env('CROW_API_TOKEN'),
    'app_id' => env('CROW_APP_ID'),
    'public_url' => env('CROW_LISTEN_PUBLIC_URL'),

    'host' => env('CROW_LISTEN_HOST'),
    'port' => env('CROW_LISTEN_PORT'),
    'listener_secret' => env('CROW_LISTEN_SECRET'),
];
