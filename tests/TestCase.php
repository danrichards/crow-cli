<?php

namespace Crow\Listen\Tests;

use Crow\Listen\CrowListenServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CrowListenServiceProvider::class];
    }
}
