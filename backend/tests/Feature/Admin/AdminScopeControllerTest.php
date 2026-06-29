<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Scope;
use App\Models\EmissionCategory;

class AdminScopeControllerTest extends TestCase
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

    public function test_superadmin_can_list_scopes()
    {
        Scope::create(['name' => 'Alcance 1', 'description' => 'Directo']);
        Scope::create(['name' => 'Alcance 2', 'description' => 'Indirecto energía']);

        $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/scopes')
             ->assertOk()
             ->assertJsonCount(2);
    }

    public function test_superadmin_can_create_scope()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/scopes', [
                 'name'        => 'Alcance 3',
                 'description' => 'Otras emisiones indirectas',
             ]);

        $response->assertCreated()
                 ->assertJsonPath('name', 'Alcance 3');

        $this->assertDatabaseHas('scopes', ['name' => 'Alcance 3']);
    }

    public function test_create_scope_requires_name_and_description()
    {
        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/scopes', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['name', 'description']);
    }

    public function test_superadmin_can_update_scope()
    {
        $scope = Scope::create(['name' => 'Original', 'description' => 'Desc']);

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/scopes/{$scope->id}", [
                 'name'        => 'Actualizado',
                 'description' => 'Nueva descripción',
             ])
             ->assertOk()
             ->assertJsonPath('name', 'Actualizado');
    }

    public function test_superadmin_can_delete_scope_with_no_categories()
    {
        $scope = Scope::create(['name' => 'Para borrar', 'description' => 'Desc']);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/scopes/{$scope->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('scopes', ['id' => $scope->id]);
    }

    public function test_cannot_delete_scope_that_has_categories()
    {
        $scope    = Scope::create(['name' => 'Con categorías', 'description' => 'Desc']);
        EmissionCategory::factory()->create(['scope_id' => $scope->id]);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/scopes/{$scope->id}")
             ->assertStatus(409)
             ->assertJsonFragment(['message' => 'Cannot delete scope with associated categories.']);
    }

    public function test_admin_cannot_access_scope_endpoints()
    {
        $this->actingAs($this->admin, 'api')
             ->getJson('/api/admin/scopes')
             ->assertForbidden();
    }
}
