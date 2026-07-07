<?php

namespace Crow\Listen\Tests;

use Crow\Listen\PlanHandoffFormatter;
use Illuminate\Support\Facades\Http;

class CrowPlanCommandTest extends TestCase
{
    public function test_plan_without_argument_lists_active_plans(): void
    {
        config()->set('crow-listen.api_url', 'https://crow.test/api/v1');
        config()->set('crow-listen.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response([
                'data' => [
                    'plans' => [
                        [
                            'plan_id' => '01KW0CYD3J0MQATPN8VMR8EVNP',
                            'status_label' => 'Ready for review',
                            'plan_type_label' => 'Feature',
                            'title' => 'Slack integration',
                            'updated_at' => '2026-07-07T15:30:00+00:00',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('crow:plan')
            ->expectsOutputToContain('01KW0CYD3J0MQATPN8VMR8EVNP')
            ->expectsOutputToContain('php artisan crow:plan <plan-id>')
            ->expectsOutputToContain('View plans on the web: https://crow.test/dashboard/implementation-plans')
            ->assertExitCode(0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/implementation-plans/handoffs'));
    }

    public function test_plan_list_markdown_sorts_by_date_desc(): void
    {
        $markdown = (new PlanHandoffFormatter())->planListMarkdown([
            'plans' => [
                [
                    'plan_id' => 'old_plan',
                    'status_label' => 'Ready for review',
                    'plan_type_label' => 'Feature',
                    'title' => 'Older plan',
                    'updated_at' => '2026-01-01T15:30:00+00:00',
                ],
                [
                    'plan_id' => 'new_plan',
                    'status_label' => 'Ready for build',
                    'plan_type_label' => 'Bug',
                    'title' => 'Newer plan',
                    'updated_at' => '2026-07-07T15:30:00+00:00',
                ],
            ],
        ]);

        $this->assertStringContainsString('| Plan ID | Date | Status | Type | Title |', $markdown);
        $this->assertStringContainsString('Jul 7th', $markdown);
        $this->assertStringContainsString('View plans on the web: https://crow.test/dashboard/implementation-plans', $markdown);
        $this->assertLessThan(strpos($markdown, 'old_plan'), strpos($markdown, 'new_plan'));
    }

    public function test_plan_without_argument_can_print_raw_json(): void
    {
        config()->set('crow-listen.api_url', 'https://crow.test/api/v1');
        config()->set('crow-listen.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response([
                'data' => [
                    'plans' => [
                        ['plan_id' => 'plan_123', 'title' => 'Slack integration'],
                    ],
                ],
            ]),
        ]);

        $this->artisan('crow:plan --json')
            ->expectsOutputToContain('"plan_id": "plan_123"')
            ->expectsOutputToContain('"title": "Slack integration"')
            ->assertExitCode(0);
    }

    public function test_plan_fetches_handoff_and_prints_markdown(): void
    {
        config()->set('crow-listen.api_url', 'https://crow.test/api/v1');
        config()->set('crow-listen.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/plan_123/handoff*' => Http::response([
                'data' => [
                    'command' => 'php artisan crow:plan plan_123',
                    'plan' => [
                        'title' => 'Slack integration',
                        'current_version' => [
                            'body_markdown' => '# Generated Plan',
                        ],
                    ],
                    'markdown' => "# Crow Plan Handoff: Slack integration\n\n# Generated Plan",
                ],
            ]),
        ]);

        $this->artisan('crow:plan plan_123')
            ->expectsOutputToContain('Crow Plan Handoff: Slack integration')
            ->expectsOutputToContain('Generated Plan')
            ->assertExitCode(0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/implementation-plans/plan_123/handoff')
            && ! str_contains($request->url(), 'app_id='));
    }

    public function test_plan_can_print_raw_json(): void
    {
        config()->set('crow-listen.api_url', 'https://crow.test/api/v1');
        config()->set('crow-listen.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/plan_123/handoff*' => Http::response([
                'data' => [
                    'plan' => ['title' => 'Slack integration'],
                    'markdown' => '# Markdown',
                ],
            ]),
        ]);

        $this->artisan('crow:plan plan_123 --json')
            ->expectsOutputToContain('"title": "Slack integration"')
            ->expectsOutputToContain('"markdown": "# Markdown"')
            ->assertExitCode(0);
    }

    public function test_plan_api_error_returns_failure(): void
    {
        config()->set('crow-listen.api_url', 'https://crow.test/api/v1');
        config()->set('crow-listen.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/missing/handoff*' => Http::response(['message' => 'Not found'], 404),
        ]);

        $this->artisan('crow:plan missing')
            ->expectsOutputToContain('Crow API request failed with HTTP 404')
            ->assertExitCode(1);
    }

    public function test_plan_without_api_token_prints_setup_instructions(): void
    {
        config()->set('crow-listen.api_url', 'https://crow.test/api/v1');
        config()->set('crow-listen.api_token', '');

        $this->artisan('crow:plan plan_123')
            ->expectsOutputToContain('CROW_API_TOKEN is not configured.')
            ->expectsOutputToContain('https://crow.test/dashboard/api-tokens')
            ->expectsOutputToContain('CROW_API_TOKEN=your_token_here')
            ->assertExitCode(1);
    }
}
