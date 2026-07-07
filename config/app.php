<?php

use App\Providers\AppServiceProvider;

return [
    'name' => 'Crow',
    'version' => app('git.version'),
    'env' => 'development',
    'providers' => [
        AppServiceProvider::class,
    ],
];
