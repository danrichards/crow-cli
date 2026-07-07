<?php

namespace Tests\Feature;

use App\Support\BrowserLauncher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthLoginCommandTest extends TestCase
{
    public function test_auth_login_verifies_and_writes_credentials(): void
    {
        $browser = new FakeBrowserLauncher();
        $this->app->instance(BrowserLauncher::class, $browser);

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response(['data' => ['plans' => []]]),
        ]);

        $this->artisan('auth login --api-token=token')
            ->expectsOutputToContain('Create a Crow API token:')
            ->expectsOutputToContain('https://crow.test/dashboard/api-tokens')
            ->expectsOutputToContain('Opening the API token page in your browser...')
            ->expectsOutputToContain('Saved Crow credentials to '.$this->crowConfigPath)
            ->assertExitCode(0);

        $this->assertSame('https://crow.test/dashboard/api-tokens', $browser->openedUrl);
        $this->assertFileExists($this->crowConfigPath);
        $this->assertSame([
            'api_token' => 'token',
            'api_url' => 'https://crow.test/api/v1',
        ], json_decode((string) file_get_contents($this->crowConfigPath), true));

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer token'));
    }

    public function test_auth_login_does_not_write_credentials_when_verification_fails(): void
    {
        $browser = new FakeBrowserLauncher(false);
        $this->app->instance(BrowserLauncher::class, $browser);

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->artisan('auth login --api-token=bad-token')
            ->expectsOutputToContain('Unable to open your browser automatically.')
            ->expectsOutputToContain('Crow API request failed with HTTP 401')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($this->crowConfigPath);
    }

    public function test_auth_login_can_skip_browser_and_url_prompt_with_options(): void
    {
        $browser = new FakeBrowserLauncher();
        $this->app->instance(BrowserLauncher::class, $browser);

        Http::fake([
            'https://custom.test/api/v1/implementation-plans/handoffs' => Http::response(['data' => ['plans' => []]]),
        ]);

        $this->artisan('auth login --api-token=token --api-url=https://custom.test/api/v1 --no-browser')
            ->expectsOutputToContain('https://custom.test/dashboard/api-tokens')
            ->expectsOutputToContain('Browser launch skipped.')
            ->expectsOutputToContain('Saved Crow credentials to '.$this->crowConfigPath)
            ->assertExitCode(0);

        $this->assertNull($browser->openedUrl);
    }

    public function test_auth_login_saves_to_current_project_by_default(): void
    {
        config()->set('crow.config_path', null);

        $project = $this->makeTempDirectory('auth-project');
        touch($project.'/composer.json');
        config()->set('crow.project_path', $project);

        $browser = new FakeBrowserLauncher();
        $this->app->instance(BrowserLauncher::class, $browser);

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response(['data' => ['plans' => []]]),
        ]);

        $projectConfig = (realpath($project) ?: $project).'/.crow/config.json';

        $this->artisan('auth login --api-token=project-token --no-browser')
            ->expectsOutputToContain('Saved Crow credentials to '.$projectConfig)
            ->assertExitCode(0);

        $this->assertFileExists($projectConfig);
        $this->assertSame([
            'api_token' => 'project-token',
            'api_url' => 'https://crow.test/api/v1',
        ], json_decode((string) file_get_contents($projectConfig), true));
    }

    public function test_auth_login_can_save_to_global_config(): void
    {
        config()->set('crow.config_path', null);

        $project = $this->makeTempDirectory('auth-global');
        touch($project.'/composer.json');
        config()->set('crow.project_path', $project);

        $browser = new FakeBrowserLauncher();
        $this->app->instance(BrowserLauncher::class, $browser);

        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response(['data' => ['plans' => []]]),
        ]);

        $this->artisan('auth login --api-token=global-token --global --no-browser')
            ->expectsOutputToContain('Saved Crow credentials to '.$this->crowGlobalConfigPath)
            ->assertExitCode(0);

        $this->assertFileExists($this->crowGlobalConfigPath);
        $this->assertFileDoesNotExist($project.'/.crow/config.json');
        $this->assertSame([
            'api_token' => 'global-token',
            'api_url' => 'https://crow.test/api/v1',
        ], json_decode((string) file_get_contents($this->crowGlobalConfigPath), true));
    }
}

class FakeBrowserLauncher extends BrowserLauncher
{
    public ?string $openedUrl = null;

    public function __construct(private readonly bool $shouldOpen = true) {}

    public function open(string $url): bool
    {
        $this->openedUrl = $url;

        return $this->shouldOpen;
    }
}
