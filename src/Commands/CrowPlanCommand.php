<?php

namespace Crow\Listen\Commands;

use Crow\Listen\CrowApiClient;
use Crow\Listen\PlanHandoffFormatter;
use Illuminate\Console\Command;
use Throwable;

class CrowPlanCommand extends Command
{
    protected $signature = 'crow:plan
        {plan? : Implementation plan slug}
        {--json : Print raw JSON instead of markdown}
        {--output= : Write output to a file instead of stdout}';

    protected $description = 'Fetch an AI-ready implementation plan handoff';

    public function handle(CrowApiClient $client, PlanHandoffFormatter $formatter): int
    {
        try {
            $plan = $this->argument('plan');

            if (! is_string($plan) || trim($plan) === '') {
                return $this->handlePlanList($client, $formatter);
            }

            $handoff = $client->fetchPlanHandoff($plan);
        } catch (Throwable $exception) {
            $this->writeMultiline($exception->getMessage(), 'error');

            return self::FAILURE;
        }

        $output = $this->option('json')
            ? $formatter->json($handoff)
            : $formatter->markdown($handoff);

        return $this->writeOutput($output, 'Crow plan handoff');
    }

    private function handlePlanList(CrowApiClient $client, PlanHandoffFormatter $formatter): int
    {
        $payload = $client->fetchPlanHandoffs();

        if ($this->option('json')) {
            return $this->writeOutput($formatter->json($payload), 'Crow plan list');
        }

        if (is_string($this->option('output')) && trim((string) $this->option('output')) !== '') {
            return $this->writeOutput($formatter->planListMarkdown($payload), 'Crow plan list');
        }

        $plans = $this->plans($payload);

        if ($plans === []) {
            $this->warn('No active implementation plans found.');

            return self::SUCCESS;
        }

        $this->writePlanTable($plans);

        $this->line('Run `php artisan crow:plan <plan-id>` to fetch a handoff.');
        $this->line('View plans on the web: '.$this->plansWebUrl());

        return self::SUCCESS;
    }

    private function writeOutput(string $output, string $label): int
    {
        $path = $this->option('output');
        if (is_string($path) && trim($path) !== '') {
            if (file_put_contents($path, $output) === false) {
                $this->error('Unable to write '.$label.' to '.$path);

                return self::FAILURE;
            }

            $this->info('Wrote '.$label.' to '.$path);

            return self::SUCCESS;
        }

        $this->writeMultiline($output);

        return self::SUCCESS;
    }

    private function plans(array $payload): array
    {
        $plans = $payload['plans'] ?? [];

        if (! is_array($plans)) {
            return [];
        }

        usort($plans, fn (array $first, array $second): int => $this->planTimestamp($second) <=> $this->planTimestamp($first));

        return $plans;
    }

    private function writePlanTable(array $plans): void
    {
        $rows = array_map(fn (array $plan): array => [
            (string) ($plan['plan_id'] ?? $plan['slug'] ?? ''),
            $this->planDate($plan),
            (string) ($plan['status_label'] ?? $plan['status'] ?? ''),
            (string) ($plan['title'] ?? 'Untitled plan'),
        ], $plans);

        $headers = ['Plan ID', 'Date', 'Status', 'Title'];
        $widths = $this->columnWidths($headers, $rows);
        $separator = $this->tableSeparator($widths);

        $this->line($separator);
        $this->line($this->tableRow($headers, $widths));
        $this->line($separator);

        foreach ($rows as $row) {
            $this->line($this->tableRow($row, $widths));
        }

        $this->line($separator);
    }

    private function columnWidths(array $headers, array $rows): array
    {
        $widths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $widths[$index] = max($widths[$index] ?? 0, strlen($value));
            }
        }

        return $widths;
    }

    private function tableRow(array $columns, array $widths): string
    {
        $cells = [];

        foreach ($columns as $index => $column) {
            $cells[] = str_pad($column, $widths[$index] ?? strlen($column));
        }

        return '| '.implode(' | ', $cells).' |';
    }

    private function tableSeparator(array $widths): string
    {
        return '+'.implode('+', array_map(fn (int $width): string => str_repeat('-', $width + 2), $widths)).'+';
    }

    private function planDate(array $plan): string
    {
        $timestamp = $plan['updated_at'] ?? null;

        if (! is_string($timestamp) || trim($timestamp) === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($timestamp);
        } catch (Throwable) {
            return '';
        }

        $day = (int) $date->format('j');

        return $date->format('M').' '.$day.$this->ordinalSuffix($day);
    }

    private function planTimestamp(array $plan): int
    {
        $timestamp = $plan['updated_at'] ?? null;

        if (! is_string($timestamp) || trim($timestamp) === '') {
            return 0;
        }

        try {
            return (new \DateTimeImmutable($timestamp))->getTimestamp();
        } catch (Throwable) {
            return 0;
        }
    }

    private function ordinalSuffix(int $day): string
    {
        if ($day >= 11 && $day <= 13) {
            return 'th';
        }

        return match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    private function plansWebUrl(): string
    {
        $base = rtrim((string) config('crow-listen.api_url'), '/');

        if (str_ends_with($base, '/api/v1')) {
            $base = substr($base, 0, -7);
        }

        return $base.'/dashboard/implementation-plans';
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
