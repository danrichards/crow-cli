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
}
