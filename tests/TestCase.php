<?php

namespace Tests;

use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $crowConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crowConfigPath = sys_get_temp_dir().'/crow-cli-test-'.bin2hex(random_bytes(8)).'/config.json';

        config()->set('crow.config_path', $this->crowConfigPath);
        config()->set('crow.api_url', null);
        config()->set('crow.api_token', null);
        config()->set('crow.app_id', null);
        config()->set('crow.public_url', null);
        config()->set('crow.host', null);
        config()->set('crow.port', null);
        config()->set('crow.listener_secret', null);
    }

    protected function tearDown(): void
    {
        if (isset($this->crowConfigPath) && is_file($this->crowConfigPath)) {
            unlink($this->crowConfigPath);
        }

        $directory = isset($this->crowConfigPath) ? dirname($this->crowConfigPath) : null;
        if (is_string($directory) && is_dir($directory)) {
            rmdir($directory);
        }

        parent::tearDown();
    }

    /** @param array<string, mixed> $values */
    protected function writeCrowConfig(array $values): void
    {
        $directory = dirname($this->crowConfigPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($this->crowConfigPath, json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}
