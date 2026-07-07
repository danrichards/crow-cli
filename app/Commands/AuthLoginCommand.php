<?php

namespace App\Commands;

use App\Support\CrowApiClient;
use App\Support\CrowConfig;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class AuthLoginCommand extends Command
{
    protected $signature = 'auth
        {action=login : Auth action to run}
        {--api-url= : Crow API URL}
        {--api-token= : Crow API token}';

    protected $description = 'Save Crow API credentials for this machine';

    public function handle(CrowApiClient $client, CrowConfig $config): int
    {
        if ($this->argument('action') !== 'login') {
            $this->error('Unknown auth action. Supported action: login');

            return self::FAILURE;
        }

        $apiUrl = $this->stringOption('api-url') ?? $config->apiUrl();
        $apiToken = $this->stringOption('api-token') ?? $this->secret('Crow API token');

        if (! is_string($apiToken) || trim($apiToken) === '') {
            $this->error('A Crow API token is required.');

            return self::FAILURE;
        }

        $apiUrl = (string) ($this->ask('Crow API URL', $apiUrl) ?: $apiUrl);

        try {
            $client->withOverrides($apiUrl, $apiToken)->verifyCredentials();
            $config->saveCredentials($apiToken, $apiUrl);
        } catch (Throwable $exception) {
            $this->writeMultiline($exception->getMessage(), 'error');

            return self::FAILURE;
        }

        $this->info('Saved Crow credentials to '.$config->configPath());

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
