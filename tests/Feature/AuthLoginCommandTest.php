<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthLoginCommandTest extends TestCase
{
    public function test_auth_login_verifies_and_writes_credentials(): void
    {
        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response(['data' => ['plans' => []]]),
        ]);

        $this->artisan('auth login --api-token=token')
            ->expectsQuestion('Crow API URL', 'https://crow.test/api/v1')
            ->expectsOutputToContain('Saved Crow credentials to '.$this->crowConfigPath)
            ->assertExitCode(0);

        $this->assertFileExists($this->crowConfigPath);
        $this->assertSame([
            'api_token' => 'token',
            'api_url' => 'https://crow.test/api/v1',
        ], json_decode((string) file_get_contents($this->crowConfigPath), true));

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer token'));
    }

    public function test_auth_login_does_not_write_credentials_when_verification_fails(): void
    {
        Http::fake([
            'https://crow.test/api/v1/implementation-plans/handoffs' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->artisan('auth login --api-token=bad-token')
            ->expectsQuestion('Crow API URL', 'https://crow.test/api/v1')
            ->expectsOutputToContain('Crow API request failed with HTTP 401')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($this->crowConfigPath);
    }
}
