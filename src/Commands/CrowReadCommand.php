<?php

namespace Crow\Listen\Commands;

use Crow\Listen\CrowApiClient;
use Crow\Listen\EventFormatter;
use Illuminate\Console\Command;

class CrowReadCommand extends Command
{
    protected $signature = 'crow:read
        {event? : Listener event ID to read}
        {--app-id= : Crow app ID}
        {--events=* : Event types to include when reading the latest unread event}
        {--json : Print raw JSON instead of markdown}
        {--leave-unread : Do not mark the event read after output}';

    protected $description = 'Read one Crow event and print an AI-agent-ready brief';

    public function handle(CrowApiClient $client, EventFormatter $formatter): int
    {
        $eventId = $this->argument('event');
        $event = $eventId
            ? $client->fetchEvent((string) $eventId)
            : $client->fetchNext($this->appId(), $this->events());

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
