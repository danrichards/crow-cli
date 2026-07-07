<?php

namespace App\Commands\Concerns;

use App\Support\CrowApiClient;

trait ResolvesCrowApiOptions
{
    private function clientWithOptions(CrowApiClient $client): CrowApiClient
    {
        return $client->withOverrides(
            $this->stringOption('api-url'),
            $this->stringOption('api-token'),
        );
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
}
