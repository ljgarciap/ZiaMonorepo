<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\MeasurementUnit;
use App\Models\EmissionFactor;

class AdminUnitControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    public function test_superadmin_can_list_units()
    {
        MeasurementUnit::create(['name' => 'Kilogramo', 'symbol' => 'kg']);
        MeasurementUnit::create(['name' => 'Tonelada', 'symbol' => 't']);

        $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/units')
             ->assertOk()
             ->assertJsonCount(2);
    }

    public function test_superadmin_can_create_unit()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/units', [
                 'name'   => 'Metro cúbico',
                 'symbol' => 'm3',
             ]);

        $response->assertCreated()
                 ->assertJsonPath('symbol', 'm3');

        $this->assertDatabaseHas('measurement_units', ['symbol' => 'm3']);
    }

    public function test_create_unit_validates_unique_symbol()
    {
        MeasurementUnit::create(['name' => 'Kilogramo', 'symbol' => 'kg']);

        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/units', ['name' => 'Kilo', 'symbol' => 'kg'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['symbol']);
    }

    public function test_superadmin_can_update_unit()
    {
        $unit = MeasurementUnit::create(['name' => 'Original', 'symbol' => 'orig']);

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/units/{$unit->id}", [
                 'name'   => 'Actualizado',
                 'symbol' => 'upd',
             ])
             ->assertOk()
             ->assertJsonPath('name', 'Actualizado');
    }

    public function test_superadmin_can_delete_unused_unit()
    {
        $unit = MeasurementUnit::create(['name' => 'Sin uso', 'symbol' => 'xu']);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/units/{$unit->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('measurement_units', ['id' => $unit->id]);
    }

    public function test_cannot_delete_unit_used_by_emission_factors()
    {
        $unit = MeasurementUnit::create(['name' => 'En uso', 'symbol' => 'eu']);
        EmissionFactor::factory()->create(['measurement_unit_id' => $unit->id]);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/units/{$unit->id}")
             ->assertStatus(409)
             ->assertJsonFragment(['message' => 'Cannot delete unit as it is used by emission factors.']);
    }

    public function test_admin_cannot_access_unit_endpoints()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'api')
             ->getJson('/api/admin/units')
             ->assertForbidden();
    }
}
