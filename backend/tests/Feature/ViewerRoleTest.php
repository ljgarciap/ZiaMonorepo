<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySector;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewerRoleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $this->company = Company::factory()->create(['company_sector_id' => $sector->id]);

        $this->viewer = User::factory()->create(['role' => 'viewer']);
        $this->viewer->companies()->attach($this->company->id, ['role' => 'viewer', 'is_active' => true]);
    }

    public function test_viewer_can_read_dashboard_summary(): void
    {
        $period = Period::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->viewer, 'api')
             ->getJson("/api/dashboard/summary?company_id={$this->company->id}&period_id={$period->id}")
             ->assertOk();
    }

    public function test_viewer_can_read_emissions_history(): void
    {
        $this->actingAs($this->viewer, 'api')
             ->getJson("/api/companies/{$this->company->id}/emissions/history")
             ->assertOk();
    }

    public function test_viewer_can_read_telemetry_live(): void
    {
        $this->actingAs($this->viewer, 'api')
             ->getJson('/api/telemetry/live')
             ->assertOk();
    }

    public function test_viewer_cannot_write_emissions(): void
    {
        $period = Period::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->viewer, 'api')
             ->postJson("/api/periods/{$period->id}/emissions", [])
             ->assertStatus(403);
    }

    public function test_viewer_cannot_use_ai_chat(): void
    {
        $this->actingAs($this->viewer, 'api')
             ->postJson('/api/ai/chat', ['message' => 'hola'])
             ->assertStatus(403);
    }

    public function test_viewer_cannot_run_simulator(): void
    {
        $this->actingAs($this->viewer, 'api')
             ->postJson('/api/simulator/calculate', [])
             ->assertStatus(403);
    }

    public function test_viewer_cannot_access_admin_routes(): void
    {
        $this->actingAs($this->viewer, 'api')
             ->getJson('/api/admin/companies')
             ->assertStatus(403);
    }

    public function test_viewer_cannot_register_iot_devices(): void
    {
        $this->actingAs($this->viewer, 'api')
             ->postJson("/api/companies/{$this->company->id}/iot-devices", ['name' => 'x', 'type' => 'energy'])
             ->assertStatus(403);
    }

    public function test_superadmin_can_create_viewer_user(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin, 'api')
             ->postJson('/api/admin/users', [
                 'name' => 'Solo Lectura',
                 'email' => 'viewer@empresa.co',
                 'role' => 'viewer',
             ])
             ->assertCreated()
             ->assertJsonPath('role', 'viewer');
    }
}
