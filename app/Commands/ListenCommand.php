<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesCrowApiOptions;
use App\Support\CrowApiClient;
use App\Support\CrowConfig;
use App\Support\EventFormatter;
use App\Support\ListenerServer;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class ListenCommand extends Command
{
    use ResolvesCrowApiOptions;

    protected $signature = 'listen
        {--host= : Host to bind}
        {--port= : Port to bind}
        {--public-url= : Public tunnel URL Crow can call}
        {--app-id= : Crow app ID}
        {--events=* : Event types to receive}
        {--json : Print raw JSON instead of markdown}
        {--leave-unread : Do not mark events read after output}
        {--no-register : Start local listener without registering with Crow}
        {--api-url= : Crow API URL}
        {--api-token= : Crow API token}';

    protected $description = 'Listen for live Crow events forwarded to this machine';

    protected $aliases = ['crow:listen'];

    public function handle(CrowApiClient $client, CrowConfig $config, EventFormatter $formatter, ListenerServer $server): int
    {
        $client = $this->clientWithOptions($client);
        $host = $config->host($this->stringOption('host'));
        $port = $config->port($this->stringOption('port'));
        $publicUrl = $config->publicUrl($this->stringOption('public-url'));
        $secret = $config->listenerSecret() ?: Str::random(48);
        $listenerId = null;

        if (! $this->option('no-register')) {
            if (! is_string($publicUrl) || trim($publicUrl) === '') {
                $this->error('CROW_LISTEN_PUBLIC_URL is not configured.');
                $this->line('Expose this listener first, then set the public URL. Example:');
                $this->line('  expose share --subdomain=your-name --server=us-2 http://127.0.0.1:'.$port);
                $this->line('  CROW_LISTEN_PUBLIC_URL=https://your-name.us-2.sharedwithexpose.com');

                return self::FAILURE;
            }

            $registration = $client->registerListener(rtrim($publicUrl, '/'), $config->appId($this->stringOption('app-id')), $this->events(), $secret);
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

    /** @return array<int, string> */
    private function events(): array
    {
        return array_values(array_filter((array) $this->option('events')));
    }
}
