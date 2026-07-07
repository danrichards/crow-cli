<?php

namespace App\Support;

use RuntimeException;

class CrowConfig
{
    public function apiUrl(?string $override = null): string
    {
        return $this->stringValue($override)
            ?? $this->stringValue((string) config('crow.api_url'))
            ?? $this->stringValue($this->stored('api_url'))
            ?? (string) config('crow.default_api_url', 'https://crow.test/api/v1');
    }

    public function apiToken(?string $override = null): ?string
    {
        return $this->stringValue($override)
            ?? $this->stringValue((string) config('crow.api_token'))
            ?? $this->stringValue($this->stored('api_token'));
    }

    public function appId(?string $override = null): ?int
    {
        $value = $this->stringValue($override)
            ?? $this->stringValue((string) config('crow.app_id'))
            ?? $this->stringValue($this->stored('app_id'));

        return is_numeric($value) ? (int) $value : null;
    }

    public function publicUrl(?string $override = null): ?string
    {
        return $this->stringValue($override)
            ?? $this->stringValue((string) config('crow.public_url'))
            ?? $this->stringValue($this->stored('public_url'));
    }

    public function host(?string $override = null): string
    {
        return $this->stringValue($override)
            ?? $this->stringValue((string) config('crow.host'))
            ?? $this->stringValue($this->stored('host'))
            ?? '127.0.0.1';
    }

    public function port(?string $override = null): int
    {
        $value = $this->stringValue($override)
            ?? $this->stringValue((string) config('crow.port'))
            ?? $this->stringValue($this->stored('port'));

        return is_numeric($value) ? (int) $value : 8787;
    }

    public function listenerSecret(?string $override = null): ?string
    {
        return $this->stringValue($override)
            ?? $this->stringValue((string) config('crow.listener_secret'))
            ?? $this->stringValue($this->stored('listener_secret'));
    }

    public function configPath(): string
    {
        return $this->readConfigPath() ?? $this->writeConfigPath();
    }

    public function saveCredentials(string $apiToken, string $apiUrl, bool $global = false): string
    {
        $path = $this->write([
            'api_token' => $apiToken,
            'api_url' => $apiUrl,
        ], $global);

        return $path;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->readConfig();
    }

    /** @param array<string, mixed> $values */
    private function write(array $values, bool $global = false): string
    {
        $path = $this->writeConfigPath($global);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create Crow config directory: '.$directory);
        }

        @chmod($directory, 0700);

        $payload = array_merge($this->all(), $values);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        if ($json === false || file_put_contents($path, $json) === false) {
            throw new RuntimeException('Unable to write Crow config file: '.$path);
        }

        @chmod($path, 0600);

        return $path;
    }

    private function stored(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, mixed> */
    private function readConfig(): array
    {
        if ($this->explicitConfigPath() !== null) {
            return $this->readConfigFile($this->explicitConfigPath());
        }

        return array_merge(
            $this->readConfigFile($this->globalConfigPath()),
            $this->readConfigFile($this->projectConfigPath()),
        );
    }

    private function readConfigPath(): ?string
    {
        if ($this->explicitConfigPath() !== null) {
            return $this->explicitConfigPath();
        }

        return $this->projectConfigPath() ?? (is_file($this->globalConfigPath()) ? $this->globalConfigPath() : null);
    }

    private function writeConfigPath(bool $global = false): string
    {
        if ($this->explicitConfigPath() !== null) {
            return $this->explicitConfigPath();
        }

        if ($global) {
            return $this->globalConfigPath();
        }

        $projectRoot = $this->projectRoot();

        if ($projectRoot !== null) {
            return $projectRoot.DIRECTORY_SEPARATOR.'.crow'.DIRECTORY_SEPARATOR.'config.json';
        }

        return $this->globalConfigPath();
    }

    /** @return array<string, mixed> */
    private function readConfigFile(?string $path): array
    {
        if (! is_string($path) || ! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function explicitConfigPath(): ?string
    {
        return $this->stringValue((string) config('crow.config_path'));
    }

    private function projectConfigPath(): ?string
    {
        $root = $this->projectRoot();

        if ($root === null) {
            return null;
        }

        $path = $root.DIRECTORY_SEPARATOR.'.crow'.DIRECTORY_SEPARATOR.'config.json';

        return is_file($path) ? $path : null;
    }

    private function projectRoot(): ?string
    {
        $directory = $this->stringValue((string) config('crow.project_path')) ?? getcwd();

        if (! is_string($directory) || trim($directory) === '') {
            return null;
        }

        $directory = realpath($directory) ?: $directory;

        while (is_string($directory) && $directory !== '') {
            if ($this->isProjectRoot($directory)) {
                return $directory;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
        }

        return null;
    }

    private function isProjectRoot(string $directory): bool
    {
        foreach (['.crow', '.git', 'composer.json', 'package.json'] as $marker) {
            if (file_exists($directory.DIRECTORY_SEPARATOR.$marker)) {
                return true;
            }
        }

        return false;
    }

    private function globalConfigPath(): string
    {
        $configured = $this->stringValue((string) config('crow.global_config_path'));

        if ($configured !== null) {
            return $configured;
        }

        return rtrim($this->homeDirectory(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.crow'.DIRECTORY_SEPARATOR.'config.json';
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function homeDirectory(): string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: $_SERVER['USERPROFILE'] ?? getenv('USERPROFILE') ?: null;

        if (! is_string($home) || trim($home) === '') {
            throw new RuntimeException('Unable to determine the user home directory for Crow config.');
        }

        return $home;
    }
}
