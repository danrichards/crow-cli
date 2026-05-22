<?php

namespace Crow\Listen\Tests;

use Illuminate\Support\Facades\Http;

class CrowReadCommandTest extends TestCase
{
    public function test_read_fetches_latest_unread_without_tunnel_config_and_marks_read(): void
    {
        config()->set('crow-listen.api_url', 'https://crow.test/api/v1');
        config()->set('crow-listen.api_token', 'token');

        Http::fake([
            'crow.test/api/v1/listener-events/next*' => Http::response([
                'data' => [
                    'id' => 'evt_123',
                    'type' => 'dispatch.received',
                    'app' => ['id' => 456, 'name' => 'App'],
                    'payload' => [
                        'kind' => 'dispatch',
                        'app' => ['id' => 456, 'name' => 'App'],
                        'dispatch' => ['title' => 'Broken checkout', 'url' => 'https://example.test'],
                    ],
                ],
            ]),
            'crow.test/api/v1/listener-events/evt_123/read' => Http::response(['data' => []]),
        ]);

        $this->artisan('crow:read')
            ->expectsOutputToContain('Crow Dispatch: Broken checkout')
            ->assertExitCode(0);

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/listener-events/evt_123/read'));
    }

    public function test_read_by_id_ignores_unread_lookup_and_leave_unread_skips_mark_read(): void
    {
        config()->set('crow-listen.api_url', 'https://crow.test/api/v1');
        config()->set('crow-listen.api_token', 'token');

        Http::fake([
            'crow.test/api/v1/listener-events/evt_123' => Http::response([
                'data' => [
                    'id' => 'evt_123',
                    'type' => 'recon.ready',
                    'app' => ['id' => 456, 'name' => 'App'],
                    'payload' => [
                        'kind' => 'recon',
                        'app' => ['id' => 456, 'name' => 'App'],
                        'recon' => ['title' => 'Checkout recon', 'brief_markdown' => '# Brief'],
                    ],
                ],
            ]),
        ]);

        $this->artisan('crow:read evt_123 --leave-unread --json')
            ->expectsOutputToContain('"id": "evt_123"')
            ->assertExitCode(0);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/read'));
    }
}
