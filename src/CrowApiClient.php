<?php

namespace Crow\Listen;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CrowApiClient
{
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

    public function fetchPlanHandoff(string $slug, ?int $appId = null): array
    {
        $response = $this->request()->get($this->url('/implementation-plans/'.$slug.'/handoff'), array_filter([
            'app_id' => $appId,
        ]));
        $this->assertSuccessful($response);

        return $response->json('data') ?? [];
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
        $token = config('crow-listen.api_token');
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
        $base = rtrim((string) config('crow-listen.api_url'), '/');
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
            'Then add it to your local .env:',
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
