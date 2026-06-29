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

    public function test_user_can_register_with_valid_data()
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'Test User',
            'email'    => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_register_fails_with_duplicate_email()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/register', [
            'name'     => 'Another User',
            'email'    => 'existing@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_with_short_password()
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'User',
            'email'    => 'valid@example.com',
            'password' => '1234',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_with_invalid_email()
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'Bad Email User',
            'email'    => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
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
