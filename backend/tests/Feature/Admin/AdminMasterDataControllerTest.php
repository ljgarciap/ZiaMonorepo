<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Scope;
use App\Models\EmissionCategory;
use App\Models\EmissionFactor;
use App\Models\MeasurementUnit;

class AdminMasterDataControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;
    private Scope $scope;
    private EmissionCategory $category;
    private MeasurementUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->admin      = User::factory()->create(['role' => 'admin']);

        $this->scope    = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Directo']);
        $this->category = EmissionCategory::factory()->create(['scope_id' => $this->scope->id]);
        $this->unit     = MeasurementUnit::firstOrCreate(['symbol' => 'kg'], ['name' => 'Kilogramo']);
    }

    // ─── categories ──────────────────────────────────────────────────────────

    public function test_superadmin_can_list_categories_including_soft_deleted()
    {
        $active  = EmissionCategory::factory()->create(['scope_id' => $this->scope->id]);
        $deleted = EmissionCategory::factory()->create(['scope_id' => $this->scope->id]);
        $deleted->delete();

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/categories');

        $response->assertOk();
        // Both active and soft-deleted should appear (withTrashed)
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($active->id));
        $this->assertTrue($ids->contains($deleted->id));
    }

    public function test_superadmin_can_create_category()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/categories', [
                 'name'     => 'Combustión Estacionaria',
                 'scope_id' => $this->scope->id,
             ]);

        $response->assertCreated()
                 ->assertJsonPath('name', 'Combustión Estacionaria');

        $this->assertDatabaseHas('emission_categories', ['name' => 'Combustión Estacionaria']);
    }

    public function test_create_category_requires_name_and_valid_scope()
    {
        // Controller uses Validator::make — response: {"scope_id": [...]}
        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/categories', ['name' => 'Sin scope'])
             ->assertUnprocessable()
             ->assertJsonStructure(['scope_id']);
    }

    public function test_create_category_rejects_nonexistent_scope()
    {
        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/categories', ['name' => 'Test', 'scope_id' => 99999])
             ->assertUnprocessable()
             ->assertJsonStructure(['scope_id']);
    }

    public function test_superadmin_can_delete_category()
    {
        $cat = EmissionCategory::factory()->create(['scope_id' => $this->scope->id]);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/categories/{$cat->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('emission_categories', ['id' => $cat->id]);
    }

    // ─── factors ─────────────────────────────────────────────────────────────

    public function test_superadmin_can_create_factor()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/factors', [
                 'emission_category_id' => $this->category->id,
                 'name'                 => 'Gas Natural Colombia',
                 'measurement_unit_id'  => $this->unit->id,
                 'factor_total_co2e'    => 1.933,
             ]);

        $response->assertCreated()
                 ->assertJsonPath('name', 'Gas Natural Colombia');

        $this->assertDatabaseHas('emission_factors', [
            'name'             => 'Gas Natural Colombia',
            'factor_total_co2e' => 1.933,
        ]);
    }

    public function test_create_factor_requires_category_and_unit()
    {
        // Controller uses Validator::make — response: {"emission_category_id": [...], ...}
        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/factors', [
                 'name'              => 'Sin categoría ni unidad',
                 'factor_total_co2e' => 1.0,
             ])
             ->assertUnprocessable()
             ->assertJsonStructure(['emission_category_id', 'measurement_unit_id']);
    }

    public function test_superadmin_can_update_factor()
    {
        $factor = EmissionFactor::factory()->create([
            'emission_category_id' => $this->category->id,
            'factor_total_co2e'    => 1.0,
        ]);

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/factors/{$factor->id}", [
                 'factor_total_co2e' => 2.5,
                 'name'              => 'Factor Actualizado',
             ])
             ->assertOk()
             ->assertJsonPath('factor_total_co2e', 2.5);
    }

    public function test_superadmin_can_delete_factor()
    {
        $factor = EmissionFactor::factory()->create(['emission_category_id' => $this->category->id]);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/factors/{$factor->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('emission_factors', ['id' => $factor->id]);
    }

    // ─── access control ──────────────────────────────────────────────────────

    public function test_admin_cannot_access_categories_endpoint()
    {
        $this->actingAs($this->admin, 'api')
             ->getJson('/api/admin/categories')
             ->assertForbidden();
    }

    public function test_admin_cannot_create_factors()
    {
        $this->actingAs($this->admin, 'api')
             ->postJson('/api/admin/factors', [
                 'emission_category_id' => $this->category->id,
                 'name'                 => 'Intento',
                 'measurement_unit_id'  => $this->unit->id,
                 'factor_total_co2e'    => 1.0,
             ])
             ->assertForbidden();
    }
}
