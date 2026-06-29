<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\CompanySector;

class CompanySectorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    public function test_superadmin_can_list_sectors()
    {
        CompanySector::create(['name' => 'Industrial', 'description' => 'Manufactura']);
        CompanySector::create(['name' => 'Comercio y Servicios', 'description' => 'Servicios']);

        $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/sectors')
             ->assertOk()
             ->assertJsonCount(2);
    }

    public function test_superadmin_can_create_sector()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/sectors', [
                 'name'        => 'Educación',
                 'description' => 'Instituciones educativas',
             ]);

        $response->assertCreated()
                 ->assertJsonPath('name', 'Educación');

        $this->assertDatabaseHas('company_sectors', ['name' => 'Educación']);
    }

    public function test_create_sector_validates_unique_name()
    {
        CompanySector::create(['name' => 'Industrial']);

        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/sectors', ['name' => 'Industrial'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['name']);
    }

    public function test_superadmin_can_show_sector()
    {
        $sector = CompanySector::create(['name' => 'Energía']);

        $this->actingAs($this->superadmin, 'api')
             ->getJson("/api/admin/sectors/{$sector->id}")
             ->assertOk()
             ->assertJsonPath('name', 'Energía');
    }

    public function test_superadmin_can_update_sector()
    {
        $sector = CompanySector::create(['name' => 'Original']);

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/sectors/{$sector->id}", [
                 'name'        => 'Actualizado',
                 'description' => 'Nueva descripción',
             ])
             ->assertOk()
             ->assertJsonPath('name', 'Actualizado');
    }

    public function test_superadmin_can_delete_sector()
    {
        $sector = CompanySector::create(['name' => 'Para borrar']);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/sectors/{$sector->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('company_sectors', ['id' => $sector->id]);
    }

    public function test_admin_cannot_access_sector_endpoints()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'api')
             ->getJson('/api/admin/sectors')
             ->assertForbidden();
    }
}
