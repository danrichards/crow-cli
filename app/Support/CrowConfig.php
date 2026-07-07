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
        $configured = $this->stringValue((string) config('crow.config_path'));

        if ($configured !== null) {
            return $configured;
        }

        return rtrim($this->homeDirectory(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.crow'.DIRECTORY_SEPARATOR.'config.json';
    }

    public function saveCredentials(string $apiToken, string $apiUrl): void
    {
        $this->write([
            'api_token' => $apiToken,
            'api_url' => $apiUrl,
        ]);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $path = $this->configPath();

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $values */
    private function write(array $values): void
    {
        $path = $this->configPath();
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
    }

    private function stored(string $key): mixed
    {
        return $this->all()[$key] ?? null;
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
