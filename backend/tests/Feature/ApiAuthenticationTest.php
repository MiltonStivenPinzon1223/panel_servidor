<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('La extensión pdo_sqlite es necesaria para las pruebas de integración locales.');
        }

        parent::setUp();
    }

    public function test_login_issues_a_revocable_token_and_authenticates_the_user(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => 'strong-password']);

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'strong-password',
            'device_name' => 'Android',
        ])->assertOk()->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'username']]);

        $this->withToken($login->json('token'))->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('username', 'admin');
        $this->withToken($login->json('token'))->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($login->json('token'))->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_is_limited_to_five_attempts_per_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'username' => 'unknown',
                'password' => 'incorrect',
                'device_name' => 'Android',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'username' => 'unknown',
            'password' => 'incorrect',
            'device_name' => 'Android',
        ])->assertTooManyRequests();
    }

    public function test_local_urls_reject_remote_hosts(): void
    {
        $user = User::factory()->create();
        $serverId = \DB::table('servers')->insertGetId([
            'name' => 'Test', 'hostname' => 'test', 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'Proyecto',
            'slug' => 'proyecto',
            'kind' => 'laravel',
            'local_url' => 'http://example.org/status',
            'exposure' => 'private',
            'environment' => 'test',
            'lifecycle' => 'active',
        ])->assertUnprocessable();

        $this->assertSame(1, \DB::table('servers')->where('id', $serverId)->count());
        $this->assertDatabaseCount('apps', 0);
    }
}
