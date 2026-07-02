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
            'factor_co2'           => 0.80,
            'factor_ch4'           => 0.05,
            'factor_n2o'           => 0.03,
        ]);

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/factors/{$factor->id}", [
                 'factor_total_co2e' => 2.5,
                 'name'              => 'Factor Actualizado',
             ])
             ->assertOk()
             ->assertJsonPath('factor_total_co2e', 2.5);

        // Partial update must not zero out gas columns not included in the request
        $this->assertDatabaseHas('emission_factors', [
            'id'         => $factor->id,
            'factor_co2' => 0.80,
            'factor_ch4' => 0.05,
            'factor_n2o' => 0.03,
        ]);
    }

    public function test_superadmin_can_delete_factor()
    {
        $factor = EmissionFactor::factory()->create(['emission_category_id' => $this->category->id]);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/factors/{$factor->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('emission_factors', ['id' => $factor->id]);
    }

    // ─── factorVersions (gap: "versionar Factores de Emisión") ────────────────

    public function test_factor_versions_lists_creation_and_updates_in_order()
    {
        // Se crea vía el endpoint (autenticado) para que LogsActivity capture el 'created';
        // un ::factory()->create() directo no queda logueado por no haber Auth::check().
        $storeResponse = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/factors', [
                 'emission_category_id' => $this->category->id,
                 'measurement_unit_id'  => $this->unit->id,
                 'name'                 => 'Diesel',
                 'factor_co2'           => 2.5,
                 'factor_total_co2e'    => 2.5,
             ]);
        $factor = EmissionFactor::find($storeResponse->json('id'));

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/factors/{$factor->id}", ['factor_co2' => 2.7]);

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson("/api/admin/factors/{$factor->id}/versions");

        $response->assertOk();
        $versions = $response->json('versions');
        $this->assertCount(2, $versions);
        $this->assertSame('created', $versions[0]['action']);
        $this->assertSame('updated', $versions[1]['action']);
        $this->assertEquals(2.5, $versions[1]['changes']['old']['factor_co2']);
        $this->assertEquals(2.7, $versions[1]['changes']['new']['factor_co2']);
    }

    public function test_admin_cannot_access_factor_versions()
    {
        $factor = EmissionFactor::factory()->create([
            'emission_category_id' => $this->category->id,
            'measurement_unit_id'  => $this->unit->id,
        ]);

        $this->actingAs($this->admin, 'api')
             ->getJson("/api/admin/factors/{$factor->id}/versions")
             ->assertForbidden();
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
