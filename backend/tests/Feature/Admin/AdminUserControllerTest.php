<?php

namespace Tests\Feature\Admin;

use App\Mail\WelcomeCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->admin      = User::factory()->create(['role' => 'admin']);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_superadmin_sees_all_users_including_soft_deleted()
    {
        $active  = User::factory()->create();
        $deleted = User::factory()->create();
        $deleted->delete();

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/users');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($active->id));
        $this->assertTrue($ids->contains($deleted->id));
    }

    public function test_admin_sees_only_users_of_their_companies()
    {
        $company      = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $this->admin->companies()->attach($company->id, ['role' => 'admin', 'is_active' => true]);

        $companyUser = User::factory()->create(['role' => 'user']);
        $companyUser->companies()->attach($company->id, ['role' => 'user', 'is_active' => true]);

        $otherUser = User::factory()->create(['role' => 'user']);
        $otherUser->companies()->attach($otherCompany->id, ['role' => 'user', 'is_active' => true]);

        $response = $this->actingAs($this->admin, 'api')
             ->getJson('/api/admin/users');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($companyUser->id));
        $this->assertFalse($ids->contains($otherUser->id));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_superadmin_can_create_user_with_any_role()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/users', [
                 'name'  => 'Nuevo Admin',
                 'email' => 'nuevo@empresa.co',
                 'role'  => 'admin',
             ]);

        $response->assertCreated()
                 ->assertJsonPath('role', 'admin');

        $this->assertDatabaseHas('users', ['email' => 'nuevo@empresa.co']);
    }

    public function test_admin_can_create_user_role_only()
    {
        $this->actingAs($this->admin, 'api')
             ->postJson('/api/admin/users', [
                 'name'  => 'Usuario Normal',
                 'email' => 'user@empresa.co',
                 'role'  => 'user',
             ])
             ->assertCreated();
    }

    public function test_admin_cannot_create_admin_or_superadmin()
    {
        $this->actingAs($this->admin, 'api')
             ->postJson('/api/admin/users', [
                 'name'  => 'Intento Admin',
                 'email' => 'intento@empresa.co',
                 'role'  => 'admin',
             ])
             ->assertForbidden();
    }

    public function test_store_validates_required_fields()
    {
        // Controller uses Validator::make — response: {"name": [...], "email": [...], "role": [...]}
        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/users', [])
             ->assertUnprocessable()
             ->assertJsonStructure(['name', 'email', 'role']);
    }

    public function test_store_restores_soft_deleted_user()
    {
        $existing = User::factory()->create(['email' => 'restore@empresa.co']);
        $existing->delete();
        $this->assertSoftDeleted('users', ['id' => $existing->id]);

        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/users', [
                 'name'  => 'Restaurado',
                 'email' => 'restore@empresa.co',
                 'role'  => 'user',
             ])
             ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $existing->id, 'deleted_at' => null]);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_superadmin_can_update_user_name_and_role()
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/users/{$user->id}", [
                 'name' => 'Nombre Actualizado',
                 'role' => 'admin',
             ])
             ->assertOk()
             ->assertJsonPath('role', 'admin');
    }

    public function test_admin_cannot_promote_user_to_admin()
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($this->admin, 'api')
             ->putJson("/api/admin/users/{$user->id}", ['role' => 'admin'])
             ->assertForbidden();
    }

    // ─── destroy & restore ────────────────────────────────────────────────────

    public function test_superadmin_can_soft_delete_user()
    {
        $user = User::factory()->create();

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/users/{$user->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_cannot_delete_yourself()
    {
        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/users/{$this->superadmin->id}")
             ->assertStatus(400)
             ->assertJsonFragment(['error' => 'Cannot delete yourself']);
    }

    public function test_superadmin_can_restore_soft_deleted_user()
    {
        $user = User::factory()->create();
        $user->delete();

        $this->actingAs($this->superadmin, 'api')
             ->postJson("/api/admin/users/{$user->id}/restore")
             ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    // ─── toggle-block (gap: "habilitar o bloquear cuentas") ────────────────────

    public function test_superadmin_can_block_a_user()
    {
        $user = User::factory()->create(['is_blocked' => false]);

        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson("/api/admin/users/{$user->id}/toggle-block");

        $response->assertOk()->assertJsonPath('is_blocked', true);

        $user->refresh();
        $this->assertTrue($user->is_blocked);
        $this->assertNotNull($user->blocked_at);
    }

    public function test_superadmin_can_unblock_a_user()
    {
        $user = User::factory()->create(['is_blocked' => true, 'blocked_at' => now()]);

        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson("/api/admin/users/{$user->id}/toggle-block");

        $response->assertOk()->assertJsonPath('is_blocked', false);

        $user->refresh();
        $this->assertFalse($user->is_blocked);
        $this->assertNull($user->blocked_at);
    }

    public function test_admin_cannot_block_a_user()
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/users/{$user->id}/toggle-block")
             ->assertStatus(403);
    }

    public function test_superadmin_cannot_block_themselves()
    {
        $this->actingAs($this->superadmin, 'api')
             ->postJson("/api/admin/users/{$this->superadmin->id}/toggle-block")
             ->assertStatus(400);
    }

    public function test_blocked_users_token_is_rejected_on_subsequent_requests()
    {
        $user = User::factory()->create(['role' => 'user', 'is_blocked' => true]);

        $this->actingAs($user, 'api')
             ->getJson('/api/dashboard/summary?company_id=1&period_id=1')
             ->assertStatus(403);
    }

    // ─── welcome email (10-1) ─────────────────────────────────────────────────

    public function test_welcome_email_sent_when_new_user_created()
    {
        Mail::fake();

        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/users', [
                 'name'  => 'Nuevo Usuario',
                 'email' => 'nuevo@empresa.co',
                 'role'  => 'user',
             ])
             ->assertCreated();

        Mail::assertSent(WelcomeCredentials::class, fn($mail) =>
            $mail->hasTo('nuevo@empresa.co')
        );
    }

    public function test_welcome_email_not_sent_when_restoring_existing_user()
    {
        Mail::fake();

        $existing = User::factory()->create(['email' => 'existente@empresa.co']);
        $existing->delete();

        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/users', [
                 'name'  => 'Restaurado',
                 'email' => 'existente@empresa.co',
                 'role'  => 'user',
             ])
             ->assertOk();

        // Restore path does not create a new credential — no mail expected
        Mail::assertNotSent(WelcomeCredentials::class);
    }
}
