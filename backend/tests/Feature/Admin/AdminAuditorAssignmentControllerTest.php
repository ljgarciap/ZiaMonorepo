<?php

namespace Tests\Feature\Admin;

use App\Models\AuditorAssignment;
use App\Models\Company;
use App\Models\CompanySector;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditorAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $auditor;
    private Company $company;
    private Period $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->auditor = User::factory()->create(['role' => 'auditor']);

        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $this->company = Company::factory()->create(['company_sector_id' => $sector->id]);
        $this->period = Period::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_superadmin_can_grant_auditor_access_to_a_period()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/auditor-assignments', [
                 'user_id' => $this->auditor->id,
                 'period_id' => $this->period->id,
             ]);

        $response->assertCreated()
                 ->assertJsonPath('user.id', $this->auditor->id)
                 ->assertJsonPath('period.id', $this->period->id);

        $this->assertDatabaseHas('auditor_assignments', [
            'user_id' => $this->auditor->id,
            'period_id' => $this->period->id,
            'company_id' => $this->company->id,
            'granted_by' => $this->superadmin->id,
        ]);
    }

    public function test_grant_rejects_user_without_auditor_role()
    {
        $regularUser = User::factory()->create(['role' => 'user']);

        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/auditor-assignments', [
                 'user_id' => $regularUser->id,
                 'period_id' => $this->period->id,
             ])
             ->assertStatus(422);
    }

    public function test_admin_cannot_grant_auditor_access()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/auditor-assignments', [
                 'user_id' => $this->auditor->id,
                 'period_id' => $this->period->id,
             ])
             ->assertStatus(403);
    }

    public function test_granting_again_for_same_period_updates_the_existing_assignment()
    {
        AuditorAssignment::factory()->create([
            'user_id' => $this->auditor->id,
            'company_id' => $this->company->id,
            'period_id' => $this->period->id,
            'granted_by' => $this->superadmin->id,
            'expires_at' => now()->subDay(), // vencido
        ]);

        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/auditor-assignments', [
                 'user_id' => $this->auditor->id,
                 'period_id' => $this->period->id,
                 'expires_at' => now()->addMonth()->toDateTimeString(),
             ])
             ->assertCreated();

        $this->assertSame(1, AuditorAssignment::where('user_id', $this->auditor->id)
            ->where('period_id', $this->period->id)->count());
    }

    public function test_superadmin_can_list_assignments()
    {
        AuditorAssignment::factory()->create([
            'user_id' => $this->auditor->id,
            'company_id' => $this->company->id,
            'period_id' => $this->period->id,
            'granted_by' => $this->superadmin->id,
        ]);

        $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/auditor-assignments')
             ->assertOk()
             ->assertJsonCount(1);
    }

    public function test_superadmin_can_revoke_an_assignment()
    {
        $assignment = AuditorAssignment::factory()->create([
            'user_id' => $this->auditor->id,
            'company_id' => $this->company->id,
            'period_id' => $this->period->id,
            'granted_by' => $this->superadmin->id,
        ]);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/auditor-assignments/{$assignment->id}")
             ->assertNoContent();

        $this->assertDatabaseMissing('auditor_assignments', ['id' => $assignment->id]);
    }
}
