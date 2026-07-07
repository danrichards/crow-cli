<?php

namespace Tests\Feature;

use App\Support\ListenerServer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ListenCommandTest extends TestCase
{
    public function test_listen_without_register_starts_local_listener_and_marks_received_events_read(): void
    {
        config()->set('crow.api_token', 'token');

        $server = new class extends ListenerServer
        {
            public string $host = '';
            public int $port = 0;

            public function listen(string $host, int $port, string $secret, callable $onEvent): void
            {
                $this->host = $host;
                $this->port = $port;

                $onEvent([
                    'id' => 'evt_listen',
                    'type' => 'dispatch.received',
                    'payload' => [
                        'kind' => 'dispatch',
                        'dispatch' => ['title' => 'Live event'],
                    ],
                ]);
            }
        };

        $this->app->instance(ListenerServer::class, $server);

        Http::fake([
            'crow.test/api/v1/listener-events/evt_listen/read' => Http::response(['data' => []]),
        ]);

        $this->artisan('listen --no-register --port=9797')
            ->expectsOutputToContain('Listening for Crow events on http://127.0.0.1:9797')
            ->expectsOutputToContain('Crow Dispatch: Live event')
            ->assertExitCode(0);

        $this->assertSame('127.0.0.1', $server->host);
        $this->assertSame(9797, $server->port);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/listener-events/evt_listen/read'));
    }

    public function test_listen_requires_public_url_when_registering(): void
    {
        config()->set('crow.api_token', 'token');

        $this->artisan('listen')
            ->expectsOutputToContain('CROW_LISTEN_PUBLIC_URL is not configured.')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }
}
