<?php

namespace Tests\Feature;

use App\Models\AuditObservation;
use App\Models\Company;
use App\Models\CompanySector;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditObservationControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Period $period;
    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $this->company = Company::factory()->create(['company_sector_id' => $sector->id]);
        $this->period  = Period::factory()->create(['company_id' => $this->company->id]);

        $this->auditor = User::factory()->create(['role' => 'auditor']);
        $this->auditor->companies()->attach($this->company->id, [
            'role' => 'auditor',
            'is_active' => true,
            'expires_at' => now()->addWeek(),
        ]);
    }

    private function url(): string
    {
        return "/api/companies/{$this->company->id}/periods/{$this->period->id}/observations";
    }

    // ─── store ──────────────────────────────────────────────────────────────

    public function test_authorized_auditor_can_create_observation(): void
    {
        $response = $this->actingAs($this->auditor, 'api')
             ->postJson($this->url(), [
                 'body' => 'El factor de emisión aplicado no coincide con la fuente declarada.',
                 'verdict' => 'observado',
             ]);

        $response->assertCreated()
                 ->assertJsonPath('body', 'El factor de emisión aplicado no coincide con la fuente declarada.')
                 ->assertJsonPath('verdict', 'observado')
                 ->assertJsonPath('user.id', $this->auditor->id);

        $this->assertDatabaseHas('audit_observations', [
            'company_id' => $this->company->id,
            'period_id' => $this->period->id,
            'user_id' => $this->auditor->id,
        ]);
    }

    public function test_auditor_with_expired_access_cannot_create_observation(): void
    {
        $this->auditor->companies()->updateExistingPivot($this->company->id, [
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($this->auditor, 'api')
             ->postJson($this->url(), ['body' => 'Hallazgo tardío'])
             ->assertStatus(403);
    }

    public function test_auditor_not_assigned_to_company_cannot_create_observation(): void
    {
        $outsider = User::factory()->create(['role' => 'auditor']);

        $this->actingAs($outsider, 'api')
             ->postJson($this->url(), ['body' => 'Hallazgo no autorizado'])
             ->assertStatus(403);
    }

    public function test_user_role_cannot_create_observation(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $user->companies()->attach($this->company->id, ['role' => 'user', 'is_active' => true]);

        $this->actingAs($user, 'api')
             ->postJson($this->url(), ['body' => 'No debería poder'])
             ->assertStatus(403);
    }

    public function test_admin_cannot_create_observation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->companies()->attach($this->company->id, ['role' => 'admin', 'is_active' => true]);

        // Matriz CRUD: Admin no crea observaciones de auditoría, solo modera (destroy).
        $this->actingAs($admin, 'api')
             ->postJson($this->url(), ['body' => 'Comentario de admin'])
             ->assertStatus(403);
    }

    public function test_body_is_required(): void
    {
        $this->actingAs($this->auditor, 'api')
             ->postJson($this->url(), [])
             ->assertStatus(422);
    }

    // ─── index ──────────────────────────────────────────────────────────────

    public function test_admin_of_company_can_list_observations(): void
    {
        AuditObservation::factory()->for($this->company)->for($this->period)->for($this->auditor)->create();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->companies()->attach($this->company->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'api')
             ->getJson($this->url())
             ->assertOk()
             ->assertJsonCount(1);
    }

    public function test_admin_of_other_company_cannot_list_observations(): void
    {
        $sector = CompanySector::create(['name' => 'Otro Sector']);
        $otherCompany = Company::factory()->create(['company_sector_id' => $sector->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->companies()->attach($otherCompany->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'api')
             ->getJson($this->url())
             ->assertStatus(403);
    }

    public function test_period_must_belong_to_company(): void
    {
        $sector = CompanySector::create(['name' => 'Otro Sector 2']);
        $otherCompany = Company::factory()->create(['company_sector_id' => $sector->id]);
        $otherPeriod = Period::factory()->create(['company_id' => $otherCompany->id]);

        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin, 'api')
             ->getJson("/api/companies/{$this->company->id}/periods/{$otherPeriod->id}/observations")
             ->assertStatus(404);
    }

    // ─── destroy ────────────────────────────────────────────────────────────

    public function test_admin_can_delete_observation_of_their_company(): void
    {
        $observation = AuditObservation::factory()->for($this->company)->for($this->period)->for($this->auditor)->create();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->companies()->attach($this->company->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'api')
             ->deleteJson("{$this->url()}/{$observation->id}")
             ->assertNoContent();

        $this->assertDatabaseMissing('audit_observations', ['id' => $observation->id]);
    }

    public function test_auditor_cannot_delete_own_observation(): void
    {
        $observation = AuditObservation::factory()->for($this->company)->for($this->period)->for($this->auditor)->create();

        $this->actingAs($this->auditor, 'api')
             ->deleteJson("{$this->url()}/{$observation->id}")
             ->assertStatus(403);
    }
}
