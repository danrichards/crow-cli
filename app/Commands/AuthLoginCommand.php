<?php

namespace App\Commands;

use App\Support\CrowApiClient;
use App\Support\BrowserLauncher;
use App\Support\CrowConfig;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class AuthLoginCommand extends Command
{
    protected $signature = 'auth
        {action=login : Auth action to run}
        {--api-url= : Crow API URL}
        {--api-token= : Crow API token}
        {--global : Save credentials globally instead of in the current project}
        {--no-browser : Do not open the API token page in a browser}';

    protected $description = 'Save Crow API credentials for this machine';

    public function handle(CrowApiClient $client, CrowConfig $config, BrowserLauncher $browser): int
    {
        if ($this->argument('action') !== 'login') {
            $this->error('Unknown auth action. Supported action: login');

            return self::FAILURE;
        }

        $apiUrl = $this->apiUrl($config);
        $tokenUrl = $this->apiTokenUrl($apiUrl);

        $this->info('Create a Crow API token:');
        $this->line('  '.$tokenUrl);

        if ($this->option('no-browser')) {
            $this->line('Browser launch skipped.');
        } elseif ($browser->open($tokenUrl)) {
            $this->line('Opening the API token page in your browser...');
        } else {
            $this->warn('Unable to open your browser automatically. Open the URL above to create a token.');
        }

        $this->newLine();

        $apiToken = $this->stringOption('api-token') ?? $this->secret('Crow API token');

        if (! is_string($apiToken) || trim($apiToken) === '') {
            $this->error('A Crow API token is required.');

            return self::FAILURE;
        }

        try {
            $client->withOverrides($apiUrl, $apiToken)->verifyCredentials();
            $path = $config->saveCredentials($apiToken, $apiUrl, (bool) $this->option('global'));
        } catch (Throwable $exception) {
            $this->writeMultiline($exception->getMessage(), 'error');

            return self::FAILURE;
        }

        $this->info('Saved Crow credentials to '.$path);

        return self::SUCCESS;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function apiUrl(CrowConfig $config): string
    {
        return $this->stringOption('api-url') ?? $config->apiUrl();
    }

    private function apiTokenUrl(string $apiUrl): string
    {
        $base = rtrim($apiUrl, '/');

        if (str_ends_with($base, '/api/v1')) {
            $base = substr($base, 0, -7);
        }

        return $base.'/dashboard/api-tokens';
    }

    private function writeMultiline(string $output, ?string $style = null): void
    {
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if ($line === '') {
                $this->newLine();

                continue;
            }

            $this->line($line, $style);
        }
    }
}
