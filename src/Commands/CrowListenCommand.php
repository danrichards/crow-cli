<?php

namespace Crow\Listen\Commands;

use Crow\Listen\CrowApiClient;
use Crow\Listen\EventFormatter;
use Crow\Listen\ListenerServer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CrowListenCommand extends Command
{
    protected $signature = 'crow:listen
        {--host= : Host to bind}
        {--port= : Port to bind}
        {--public-url= : Public tunnel URL Crow can call}
        {--app-id= : Crow app ID}
        {--events=* : Event types to receive}
        {--json : Print raw JSON instead of markdown}
        {--leave-unread : Do not mark events read after output}
        {--no-register : Start local listener without registering with Crow}';

    protected $description = 'Listen for live Crow events forwarded to this machine';

    public function handle(CrowApiClient $client, EventFormatter $formatter, ListenerServer $server): int
    {
        $host = (string) ($this->option('host') ?: config('crow-listen.host', '127.0.0.1'));
        $port = (int) ($this->option('port') ?: config('crow-listen.port', 8787));
        $publicUrl = $this->option('public-url') ?: config('crow-listen.public_url');
        $secret = (string) (config('crow-listen.listener_secret') ?: Str::random(48));
        $listenerId = null;

        if (! $this->option('no-register')) {
            if (! is_string($publicUrl) || trim($publicUrl) === '') {
                $this->error('CROW_LISTEN_PUBLIC_URL is not configured.');
                $this->line('Expose this listener first, then set the public URL. Example:');
                $this->line('  expose share --subdomain=your-name --server=us-2 http://127.0.0.1:'.$port);
                $this->line('  CROW_LISTEN_PUBLIC_URL=https://your-name.us-2.sharedwithexpose.com');

                return self::FAILURE;
            }

            $registration = $client->registerListener(rtrim((string) $publicUrl, '/'), $this->appId(), $this->events(), $secret);
            $listenerId = $registration['id'] ?? null;
            $this->info('Registered Crow listener #'.$listenerId.' for '.$publicUrl);
        }

        $this->info("Listening for Crow events on http://{$host}:{$port}");

        try {
            $server->listen($host, $port, $secret, function (array $event) use ($client, $formatter): void {
                $this->line($this->option('json') ? $formatter->json($event) : $formatter->markdown($event));
                $this->newLine();

                if (! $this->option('leave-unread') && isset($event['id'])) {
                    $client->markRead((string) $event['id']);
                }
            });
        } finally {
            if ($listenerId && ! $this->option('no-register')) {
                $client->unregisterListener($listenerId);
                $this->info('Unregistered Crow listener #'.$listenerId);
            }
        }

        return self::SUCCESS;
    }

    private function appId(): ?int
    {
        $value = $this->option('app-id') ?: config('crow-listen.app_id');

        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<int, string> */
    private function events(): array
    {
        return array_values(array_filter((array) $this->option('events')));
    }
}
