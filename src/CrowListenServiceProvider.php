<?php

namespace Crow\Listen;

use Crow\Listen\Commands\CrowListenCommand;
use Crow\Listen\Commands\CrowReadCommand;
use Illuminate\Support\ServiceProvider;

class CrowListenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/crow-listen.php', 'crow-listen');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/crow-listen.php' => config_path('crow-listen.php'),
        ], 'crow-listen-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CrowReadCommand::class,
                CrowListenCommand::class,
            ]);
        }
    }
}
