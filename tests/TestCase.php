<?php

namespace Tests;

use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $crowConfigPath;
    protected string $crowGlobalConfigPath;

    /** @var array<int, string> */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crowConfigPath = sys_get_temp_dir().'/crow-cli-test-'.bin2hex(random_bytes(8)).'/config.json';
        $this->crowGlobalConfigPath = sys_get_temp_dir().'/crow-cli-global-test-'.bin2hex(random_bytes(8)).'/config.json';

        config()->set('crow.config_path', $this->crowConfigPath);
        config()->set('crow.global_config_path', $this->crowGlobalConfigPath);
        config()->set('crow.project_path', null);
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
        foreach ([$this->crowConfigPath ?? null, $this->crowGlobalConfigPath ?? null] as $path) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }

            $directory = is_string($path) ? dirname($path) : null;
            if (is_string($directory) && is_dir($directory)) {
                rmdir($directory);
            }
        }

        foreach (array_reverse($this->temporaryDirectories) as $directory) {
            $this->removeDirectory($directory);
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

    /** @param array<string, mixed> $values */
    protected function writeGlobalCrowConfig(array $values): void
    {
        $directory = dirname($this->crowGlobalConfigPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($this->crowGlobalConfigPath, json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    protected function makeTempDirectory(string $name = 'project'): string
    {
        $directory = sys_get_temp_dir().'/crow-cli-'.$name.'-'.bin2hex(random_bytes(8));

        mkdir($directory, 0700, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
