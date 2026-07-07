<?php

namespace Crow\Listen;

class PlanHandoffFormatter
{
    public function markdown(array $handoff): string
    {
        $markdown = trim((string) ($handoff['markdown'] ?? ''));

        if ($markdown !== '') {
            return $markdown;
        }

        $plan = $handoff['plan'] ?? [];
        $title = $plan['title'] ?? 'Untitled plan';
        $body = trim((string) data_get($plan, 'current_version.body_markdown', ''));

        return trim(implode("\n", [
            '# Crow Plan Handoff: '.$title,
            '',
            $body !== '' ? $body : 'No generated plan content is available.',
        ]));
    }

    public function json(array $handoff): string
    {
        return json_encode($handoff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function planListMarkdown(array $payload): string
    {
        $plans = $this->plans($payload);

        if ($plans === []) {
            return 'No active implementation plans found.';
        }

        $lines = [
            '| Plan ID | Date | Status | Type | Title |',
            '| --- | --- | --- | --- | --- |',
        ];

        foreach ($plans as $plan) {
            $lines[] = '| '.implode(' | ', [
                $this->tableCell((string) ($plan['plan_id'] ?? $plan['slug'] ?? '')),
                $this->tableCell($this->planDate($plan)),
                $this->tableCell((string) ($plan['status_label'] ?? $plan['status'] ?? '')),
                $this->tableCell((string) ($plan['plan_type_label'] ?? $plan['plan_type'] ?? '')),
                $this->tableCell((string) ($plan['title'] ?? 'Untitled plan')),
            ]).' |';
        }

        $lines[] = '';
        $lines[] = 'Run `php artisan crow:plan <plan-id>` to fetch a handoff.';

        return implode("\n", $lines);
    }

    private function tableCell(string $value): string
    {
        return str_replace('|', '\|', $value);
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

    private function planDate(array $plan): string
    {
        $timestamp = $plan['updated_at'] ?? null;

        if (! is_string($timestamp) || trim($timestamp) === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($timestamp);
        } catch (\Throwable) {
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
        } catch (\Throwable) {
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
}
