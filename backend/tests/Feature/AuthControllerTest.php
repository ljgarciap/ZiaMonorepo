<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a Passport personal access client so token generation works in tests.
        // Passport RSA keys must exist on disk (CI runs `passport:keys --force`).
        \Laravel\Passport\Client::factory()->asPersonalAccessTokenClient()->create();
    }

    // 10-6: public self-registration is disabled — users are created by admins via POST /admin/users
    public function test_register_endpoint_is_disabled()
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'Test User',
            'email'    => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    public function test_user_can_login_with_correct_credentials()
    {
        // User needs a role that provides at least one context (admin = global context)
        $user = User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'role'     => 'admin',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token']);
    }

    public function test_login_fails_with_wrong_password()
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => bcrypt('correctpass'),
            'role'     => 'admin',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'user@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_with_nonexistent_email()
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'nobody@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout()
    {
        $user  = User::factory()->create(['role' => 'admin']);
        $token = $user->createToken('test-token')->accessToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                         ->postJson('/api/logout');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Successfully logged out']);
    }
}
