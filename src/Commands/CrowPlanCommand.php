<?php

namespace Crow\Listen\Commands;

use Crow\Listen\CrowApiClient;
use Crow\Listen\PlanHandoffFormatter;
use Illuminate\Console\Command;
use Throwable;

class CrowPlanCommand extends Command
{
    protected $signature = 'crow:plan
        {plan : Implementation plan slug}
        {--json : Print raw JSON instead of markdown}
        {--output= : Write output to a file instead of stdout}';

    protected $description = 'Fetch an AI-ready implementation plan handoff';

    public function handle(CrowApiClient $client, PlanHandoffFormatter $formatter): int
    {
        try {
            $handoff = $client->fetchPlanHandoff((string) $this->argument('plan'));
        } catch (Throwable $exception) {
            $this->writeMultiline($exception->getMessage(), 'error');

            return self::FAILURE;
        }

        $output = $this->option('json')
            ? $formatter->json($handoff)
            : $formatter->markdown($handoff);

        $path = $this->option('output');
        if (is_string($path) && trim($path) !== '') {
            if (file_put_contents($path, $output) === false) {
                $this->error('Unable to write Crow plan handoff to '.$path);

                return self::FAILURE;
            }

            $this->info('Wrote Crow plan handoff to '.$path);

            return self::SUCCESS;
        }

        $this->writeMultiline($output);

        return self::SUCCESS;
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
