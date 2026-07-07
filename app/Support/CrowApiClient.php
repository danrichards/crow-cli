<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CrowApiClient
{
    public function __construct(
        private readonly CrowConfig $config,
        private ?string $apiUrl = null,
        private ?string $apiToken = null,
    ) {}

    public function withOverrides(?string $apiUrl = null, ?string $apiToken = null): self
    {
        $client = clone $this;
        $client->apiUrl = $apiUrl;
        $client->apiToken = $apiToken;

        return $client;
    }

    public function verifyCredentials(): void
    {
        $response = $this->request()->get($this->url('/implementation-plans/handoffs'));
        $this->assertSuccessful($response);
    }

    public function fetchNext(?int $appId = null, array $events = []): ?array
    {
        $response = $this->request()->get($this->url('/listener-events/next'), array_filter([
            'app_id' => $appId,
            'events' => $events ?: null,
        ]));

        if ($response->status() === 404) {
            return null;
        }

        $this->assertSuccessful($response);

        return $response->json('data');
    }

    public function fetchEvent(string $event): array
    {
        $response = $this->request()->get($this->url('/listener-events/'.$event));
        $this->assertSuccessful($response);

        return $response->json('data') ?? [];
    }

    public function fetchPlanHandoff(string $slug): array
    {
        $response = $this->request()->get($this->url('/implementation-plans/'.$slug.'/handoff'));
        $this->assertSuccessful($response);

        return $response->json('data') ?? [];
    }

    public function fetchPlanHandoffs(): array
    {
        $response = $this->request()->get($this->url('/implementation-plans/handoffs'));
        $this->assertSuccessful($response);

        return $response->json('data') ?? ['plans' => []];
    }

    public function markRead(string $event): void
    {
        $response = $this->request()->patch($this->url('/listener-events/'.$event.'/read'));
        $this->assertSuccessful($response);
    }

    public function registerListener(string $publicUrl, ?int $appId, array $events, string $secret): array
    {
        $response = $this->request()->post($this->url('/listeners'), array_filter([
            'public_url' => $publicUrl,
            'app_id' => $appId,
            'events' => $events ?: null,
            'secret' => $secret,
        ]));

        $this->assertSuccessful($response);

        return $response->json('data') ?? [];
    }

    public function unregisterListener(int|string $listenerId): void
    {
        $response = $this->request()->delete($this->url('/listeners/'.$listenerId));
        $this->assertSuccessful($response);
    }

    private function request()
    {
        $token = $this->config->apiToken($this->apiToken);

        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException($this->missingTokenMessage());
        }

        return Http::acceptJson()->asJson()->withToken($token);
    }

    private function url(string $path): string
    {
        return $this->apiBaseUrl().$path;
    }

    private function apiBaseUrl(): string
    {
        $base = rtrim($this->config->apiUrl($this->apiUrl), '/');

        if (! str_ends_with($base, '/api/v1')) {
            $base .= '/api/v1';
        }

        return $base;
    }

    private function appBaseUrl(): string
    {
        return preg_replace('#/api/v1$#', '', $this->apiBaseUrl()) ?: $this->apiBaseUrl();
    }

    private function missingTokenMessage(): string
    {
        return implode(PHP_EOL, [
            'CROW_API_TOKEN is not configured.',
            '',
            'Create a Crow API token:',
            '  '.$this->appBaseUrl().'/dashboard/api-tokens',
            '',
            'Then run:',
            '  crow auth login',
            '',
            'For automation, you can still set:',
            '  CROW_API_TOKEN=your_token_here',
            '',
            'Optional if you are not using the default Crow URL:',
            '  CROW_API_URL='.$this->apiBaseUrl(),
            '',
            'Re-run the command after saving the token.',
        ]);
    }

    private function assertSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new RuntimeException('Crow API request failed with HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500));
    }
}
