<?php

namespace App\Commands;

use App\Commands\Concerns\ResolvesCrowApiOptions;
use App\Support\CrowApiClient;
use App\Support\CrowConfig;
use App\Support\EventFormatter;
use LaravelZero\Framework\Commands\Command;

class ReadCommand extends Command
{
    use ResolvesCrowApiOptions;

    protected $signature = 'read
        {event? : Listener event ID to read}
        {--app-id= : Crow app ID}
        {--events=* : Event types to include when reading the latest unread event}
        {--json : Print raw JSON instead of markdown}
        {--leave-unread : Do not mark the event read after output}
        {--api-url= : Crow API URL}
        {--api-token= : Crow API token}';

    protected $description = 'Read one Crow event and print an AI-agent-ready brief';

    protected $aliases = ['crow:read'];

    public function handle(CrowApiClient $client, CrowConfig $config, EventFormatter $formatter): int
    {
        $client = $this->clientWithOptions($client);
        $eventId = $this->argument('event');
        $event = $eventId
            ? $client->fetchEvent((string) $eventId)
            : $client->fetchNext($config->appId($this->stringOption('app-id')), $this->events());

        if (! $event) {
            $this->warn('No unread Crow events found.');

            return self::SUCCESS;
        }

        $this->line($this->option('json') ? $formatter->json($event) : $formatter->markdown($event));

        if (! $this->option('leave-unread') && isset($event['id'])) {
            $client->markRead((string) $event['id']);
        }

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function events(): array
    {
        return array_values(array_filter((array) $this->option('events')));
    }
}
