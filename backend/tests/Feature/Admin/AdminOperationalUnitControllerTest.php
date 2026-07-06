<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\OperationalUnit;

class AdminOperationalUnitControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->companies()->attach($this->company->id, ['role' => 'admin', 'is_active' => true]);
    }

    // ─── camino feliz (empresa propia) ────────────────────────────────────────

    public function test_admin_can_list_units_of_their_own_company()
    {
        OperationalUnit::create(['company_id' => $this->company->id, 'name' => 'Planta Norte']);

        $response = $this->actingAs($this->admin, 'api')
             ->getJson("/api/companies/{$this->company->id}/units");

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_admin_can_create_a_unit_for_their_own_company()
    {
        $response = $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$this->company->id}/units", ['name' => 'Planta Sur']);

        $response->assertCreated()->assertJsonPath('name', 'Planta Sur');
    }

    // ─── IDOR: nada de esto debe funcionar cross-tenant ──────────────────────

    public function test_admin_cannot_list_units_of_another_company()
    {
        $otherCompany = Company::factory()->create();

        $this->actingAs($this->admin, 'api')
             ->getJson("/api/companies/{$otherCompany->id}/units")
             ->assertStatus(403);
    }

    public function test_admin_cannot_create_a_unit_for_another_company()
    {
        $otherCompany = Company::factory()->create();

        $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$otherCompany->id}/units", ['name' => 'Intrusa'])
             ->assertStatus(403);

        $this->assertDatabaseMissing('operational_units', ['company_id' => $otherCompany->id, 'name' => 'Intrusa']);
    }

    public function test_admin_cannot_update_a_unit_of_another_company()
    {
        $otherCompany = Company::factory()->create();
        $unit = OperationalUnit::create(['company_id' => $otherCompany->id, 'name' => 'Original']);

        $this->actingAs($this->admin, 'api')
             ->putJson("/api/admin/companies/{$otherCompany->id}/units/{$unit->id}", ['name' => 'Hackeada'])
             ->assertStatus(403);

        $this->assertDatabaseHas('operational_units', ['id' => $unit->id, 'name' => 'Original']);
    }

    public function test_admin_cannot_delete_a_unit_of_another_company()
    {
        $otherCompany = Company::factory()->create();
        $unit = OperationalUnit::create(['company_id' => $otherCompany->id, 'name' => 'A borrar']);

        $this->actingAs($this->admin, 'api')
             ->deleteJson("/api/admin/companies/{$otherCompany->id}/units/{$unit->id}")
             ->assertStatus(403);

        $this->assertDatabaseHas('operational_units', ['id' => $unit->id, 'deleted_at' => null]);
    }

    public function test_admin_cannot_assign_a_user_to_a_unit_of_another_company()
    {
        $otherCompany = Company::factory()->create();
        $unit = OperationalUnit::create(['company_id' => $otherCompany->id, 'name' => 'Unidad']);
        $victim = User::factory()->create(['role' => 'user']);

        $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$otherCompany->id}/units/{$unit->id}/assign", ['user_id' => $victim->id])
             ->assertStatus(403);
    }

    public function test_admin_cannot_unassign_a_user_from_a_unit_of_another_company()
    {
        $otherCompany = Company::factory()->create();
        $unit = OperationalUnit::create(['company_id' => $otherCompany->id, 'name' => 'Unidad']);
        $victim = User::factory()->create(['role' => 'user']);

        $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$otherCompany->id}/units/{$unit->id}/unassign", ['user_id' => $victim->id])
             ->assertStatus(403);
    }

    public function test_superadmin_can_manage_units_of_any_company()
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $otherCompany = Company::factory()->create();

        $this->actingAs($superadmin, 'api')
             ->postJson("/api/admin/companies/{$otherCompany->id}/units", ['name' => 'Legítima'])
             ->assertCreated();
    }
}
