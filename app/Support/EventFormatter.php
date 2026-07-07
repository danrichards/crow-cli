<?php

namespace App\Support;

class EventFormatter
{
    public function markdown(array $event): string
    {
        $payload = $event['payload'] ?? [];
        $kind = $payload['kind'] ?? null;

        return $kind === 'recon'
            ? $this->reconMarkdown($event, $payload)
            : $this->dispatchMarkdown($event, $payload);
    }

    public function json(array $event): string
    {
        return json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function dispatchMarkdown(array $event, array $payload): string
    {
        $dispatch = $payload['dispatch'] ?? [];
        $app = $payload['app'] ?? $event['app'] ?? [];
        $tickets = $payload['tickets'] ?? [];

        $lines = [
            '# Crow Dispatch: '.($dispatch['title'] ?: 'Untitled dispatch'),
            '',
            '- Event: '.($event['type'] ?? 'unknown'),
            '- Event ID: '.($event['id'] ?? 'unknown'),
            '- App: '.($app['name'] ?? 'Unknown').' (#'.($app['id'] ?? 'unknown').')',
        ];

        foreach ([
            'Source' => $dispatch['url'] ?? null,
            'Error type' => $dispatch['error_type'] ?? null,
            'Category' => $dispatch['category'] ?? null,
            'Priority' => $dispatch['priority'] ?? null,
            'Status' => $dispatch['status'] ?? null,
        ] as $label => $value) {
            if ($value) {
                $lines[] = "- {$label}: {$value}";
            }
        }

        if ($tickets) {
            $lines[] = '';
            $lines[] = '## Tickets';
            foreach ($tickets as $ticket) {
                $lines[] = '- '.($ticket['provider'] ?? 'ticket').': '.($ticket['key'] ?? 'unknown').' '.($ticket['url'] ?? '');
            }
        }

        $markdown = trim((string) ($dispatch['ai_description_markdown'] ?? $dispatch['markdown'] ?? ''));
        if ($markdown !== '') {
            $lines[] = '';
            $lines[] = '## Evidence';
            $lines[] = $markdown;
        }

        $lines[] = '';
        $lines[] = '## Suggested Next Action';
        $lines[] = 'Use the evidence above to reproduce, diagnose, and implement the smallest focused fix or follow-up plan.';

        return implode("\n", $lines);
    }

    private function reconMarkdown(array $event, array $payload): string
    {
        $recon = $payload['recon'] ?? [];
        $app = $payload['app'] ?? $event['app'] ?? [];
        $brief = trim((string) ($recon['brief_markdown'] ?? ''));

        if ($brief !== '') {
            return $brief."\n\n".implode("\n", [
                '---',
                '- Crow Event: '.($event['type'] ?? 'unknown'),
                '- Event ID: '.($event['id'] ?? 'unknown'),
                '- App: '.($app['name'] ?? 'Unknown').' (#'.($app['id'] ?? 'unknown').')',
            ]);
        }

        return implode("\n", [
            '# Crow Recon: '.($recon['title'] ?? 'Untitled recon'),
            '',
            '- Event: '.($event['type'] ?? 'unknown'),
            '- Event ID: '.($event['id'] ?? 'unknown'),
            '- App: '.($app['name'] ?? 'Unknown').' (#'.($app['id'] ?? 'unknown').')',
            '- Intent: '.($recon['intent'] ?? 'unknown'),
            '- Source: '.($recon['origin_url'] ?? 'unknown'),
            '',
            '## Suggested Next Action',
            'Review the Recon recording and artifacts, then turn the captured behavior into a focused implementation plan.',
        ]);
    }
}
