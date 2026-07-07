<?php

namespace Tests\Feature;

use App\Support\PlanHandoffFormatter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlanCommandTest extends TestCase
{
    public function test_plan_without_argument_lists_active_plans(): void
    {
        config()->set('crow.api_token', 'token');

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

        $this->artisan('plan')
            ->expectsOutputToContain('01KW0CYD3J0MQATPN8VMR8EVNP')
            ->expectsOutputToContain('crow plan <plan-id>')
            ->expectsOutputToContain('View plans on the web: https://crow.test/dashboard/implementation-plans')
            ->assertExitCode(0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/implementation-plans/handoffs'));
    }

    public function test_plan_compatibility_alias_lists_active_plans(): void
    {
        config()->set('crow.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response([
                'data' => [
                    'plans' => [
                        ['plan_id' => 'plan_alias', 'title' => 'Alias plan'],
                    ],
                ],
            ]),
        ]);

        $this->artisan('crow:plan')
            ->expectsOutputToContain('plan_alias')
            ->assertExitCode(0);
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
        $this->assertStringContainsString('Run `crow plan <plan-id>` to fetch a handoff.', $markdown);
        $this->assertLessThan(strpos($markdown, 'old_plan'), strpos($markdown, 'new_plan'));
    }

    public function test_plan_without_argument_can_print_raw_json(): void
    {
        config()->set('crow.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response([
                'data' => [
                    'plans' => [
                        ['plan_id' => 'plan_123', 'title' => 'Slack integration'],
                    ],
                ],
            ]),
        ]);

        $this->artisan('plan --json')
            ->expectsOutputToContain('"plan_id": "plan_123"')
            ->expectsOutputToContain('"title": "Slack integration"')
            ->assertExitCode(0);
    }

    public function test_plan_fetches_handoff_and_prints_markdown(): void
    {
        config()->set('crow.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/plan_123/handoff*' => Http::response([
                'data' => [
                    'command' => 'crow plan plan_123',
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

        $this->artisan('plan plan_123')
            ->expectsOutputToContain('Crow Plan Handoff: Slack integration')
            ->expectsOutputToContain('Generated Plan')
            ->assertExitCode(0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/implementation-plans/plan_123/handoff')
            && ! str_contains($request->url(), 'app_id='));
    }

    public function test_plan_can_print_raw_json(): void
    {
        config()->set('crow.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/plan_123/handoff*' => Http::response([
                'data' => [
                    'plan' => ['title' => 'Slack integration'],
                    'markdown' => '# Markdown',
                ],
            ]),
        ]);

        $this->artisan('plan plan_123 --json')
            ->expectsOutputToContain('"title": "Slack integration"')
            ->expectsOutputToContain('"markdown": "# Markdown"')
            ->assertExitCode(0);
    }

    public function test_plan_api_error_returns_failure(): void
    {
        config()->set('crow.api_token', 'token');

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/missing/handoff*' => Http::response(['message' => 'Not found'], 404),
        ]);

        $this->artisan('plan missing')
            ->expectsOutputToContain('Crow API request failed with HTTP 404')
            ->assertExitCode(1);
    }

    public function test_plan_without_api_token_prints_setup_instructions(): void
    {
        $this->artisan('plan plan_123')
            ->expectsOutputToContain('CROW_API_TOKEN is not configured.')
            ->expectsOutputToContain('https://crow.test/dashboard/api-tokens')
            ->expectsOutputToContain('crow auth login')
            ->assertExitCode(1);
    }

    public function test_env_config_overrides_stored_credentials(): void
    {
        $this->writeCrowConfig([
            'api_url' => 'https://stored.test/api/v1',
            'api_token' => 'stored-token',
        ]);

        config()->set('crow.api_url', 'https://env.test/api/v1');
        config()->set('crow.api_token', 'env-token');

        Http::fake([
            'https://env.test/api/v1/implementation-plans/handoffs' => Http::response([
                'data' => ['plans' => []],
            ]),
        ]);

        $this->artisan('plan')->assertExitCode(0);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://env.test/api/v1/implementation-plans/handoffs'
            && $request->hasHeader('Authorization', 'Bearer env-token'));
    }

    public function test_nearest_project_config_overrides_global_config(): void
    {
        config()->set('crow.config_path', null);

        $project = $this->makeTempDirectory('project-config');
        mkdir($project.'/.crow');
        mkdir($project.'/packages');
        mkdir($project.'/packages/client');
        touch($project.'/composer.json');
        file_put_contents($project.'/.crow/config.json', json_encode([
            'api_url' => 'https://project.test/api/v1',
            'api_token' => 'project-token',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->writeGlobalCrowConfig([
            'api_url' => 'https://global.test/api/v1',
            'api_token' => 'global-token',
        ]);

        config()->set('crow.project_path', $project.'/packages/client');

        Http::fake([
            'https://project.test/api/v1/implementation-plans/handoffs' => Http::response([
                'data' => ['plans' => []],
            ]),
        ]);

        $this->artisan('plan')->assertExitCode(0);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://project.test/api/v1/implementation-plans/handoffs'
            && $request->hasHeader('Authorization', 'Bearer project-token'));
    }

    public function test_env_config_overrides_project_config(): void
    {
        config()->set('crow.config_path', null);
        config()->set('crow.api_url', 'https://env.test/api/v1');
        config()->set('crow.api_token', 'env-token');

        $project = $this->makeTempDirectory('env-over-project');
        mkdir($project.'/.crow');
        touch($project.'/composer.json');
        file_put_contents($project.'/.crow/config.json', json_encode([
            'api_url' => 'https://project.test/api/v1',
            'api_token' => 'project-token',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        config()->set('crow.project_path', $project);

        Http::fake([
            'https://env.test/api/v1/implementation-plans/handoffs' => Http::response([
                'data' => ['plans' => []],
            ]),
        ]);

        $this->artisan('plan')->assertExitCode(0);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://env.test/api/v1/implementation-plans/handoffs'
            && $request->hasHeader('Authorization', 'Bearer env-token'));
    }

    public function test_explicit_options_override_env_and_stored_credentials(): void
    {
        $this->writeCrowConfig([
            'api_url' => 'https://stored.test/api/v1',
            'api_token' => 'stored-token',
        ]);

        config()->set('crow.api_url', 'https://env.test/api/v1');
        config()->set('crow.api_token', 'env-token');

        Http::fake([
            'https://option.test/api/v1/implementation-plans/handoffs' => Http::response([
                'data' => ['plans' => []],
            ]),
        ]);

        $this->artisan('plan --api-url=https://option.test/api/v1 --api-token=option-token')->assertExitCode(0);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://option.test/api/v1/implementation-plans/handoffs'
            && $request->hasHeader('Authorization', 'Bearer option-token'));
    }
}
