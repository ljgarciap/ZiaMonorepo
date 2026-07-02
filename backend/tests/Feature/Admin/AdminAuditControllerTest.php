<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    // ─── P2: filtro server-side de evento crítico ──────────────────────────────

    public function test_critical_event_deletion_filters_only_deleted_actions(): void
    {
        ActivityLog::create(['user_id' => $this->superadmin->id, 'action' => 'created', 'model' => \App\Models\Company::class, 'model_id' => 1]);
        ActivityLog::create(['user_id' => $this->superadmin->id, 'action' => 'deleted', 'model' => \App\Models\Company::class, 'model_id' => 2]);

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/audit-logs?critical_event=deletion');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('deleted', $data[0]['action']);
    }

    public function test_critical_event_role_change_filters_by_user_model(): void
    {
        ActivityLog::create(['user_id' => $this->superadmin->id, 'action' => 'updated', 'model' => \App\Models\User::class, 'model_id' => 1]);
        ActivityLog::create(['user_id' => $this->superadmin->id, 'action' => 'updated', 'model' => \App\Models\Company::class, 'model_id' => 1]);

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/audit-logs?critical_event=role_change');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(\App\Models\User::class, $data[0]['model']);
    }

    public function test_critical_event_pagination_total_reflects_filtered_count(): void
    {
        ActivityLog::create(['user_id' => $this->superadmin->id, 'action' => 'deleted', 'model' => \App\Models\Company::class, 'model_id' => 1]);
        ActivityLog::create(['user_id' => $this->superadmin->id, 'action' => 'created', 'model' => \App\Models\Company::class, 'model_id' => 2]);
        ActivityLog::create(['user_id' => $this->superadmin->id, 'action' => 'created', 'model' => \App\Models\Company::class, 'model_id' => 3]);

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/audit-logs?critical_event=deletion');

        $response->assertOk()->assertJsonPath('total', 1);
    }

    // ─── P2: acceso excepcional (superadmin en contexto admin) ─────────────────

    public function test_superadmin_acting_as_admin_marks_log_as_exceptional(): void
    {
        // Company writes are superadmin-only (A02); use a users write, which a
        // downgraded superadmin (X-Context-Role: admin) is still allowed to perform.
        $target = User::factory()->create(['role' => 'user', 'name' => 'Original']);

        $this->actingAs($this->superadmin, 'api')
             ->withHeaders(['X-Context-Role' => 'admin'])
             ->putJson("/api/admin/users/{$target->id}", ['name' => 'Actualizado'])
             ->assertOk();

        $log = ActivityLog::where('model', User::class)
            ->where('model_id', $target->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->is_exceptional);
    }

    public function test_superadmin_acting_as_superadmin_is_not_exceptional(): void
    {
        $target = User::factory()->create(['role' => 'user', 'name' => 'Original']);

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/users/{$target->id}", ['name' => 'Actualizado'])
             ->assertOk();

        $log = ActivityLog::where('model', User::class)
            ->where('model_id', $target->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertFalse($log->is_exceptional);
    }

    // ─── companyIndex: bitácora scoped a empresa, acceso del Auditor externo ───

    public function test_authorized_auditor_can_read_company_scoped_audit_log(): void
    {
        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $company = Company::factory()->create(['company_sector_id' => $sector->id]);

        $auditor = User::factory()->create(['role' => 'auditor']);
        $auditor->companies()->attach($company->id, [
            'role' => 'auditor', 'is_active' => true, 'expires_at' => now()->addWeek(),
        ]);

        $companyUser = User::factory()->create(['role' => 'user']);
        $companyUser->companies()->attach($company->id, ['role' => 'user', 'is_active' => true]);

        ActivityLog::create(['user_id' => $companyUser->id, 'action' => 'updated', 'model' => Company::class, 'model_id' => $company->id]);

        $this->actingAs($auditor, 'api')
             ->getJson("/api/companies/{$company->id}/audit-logs")
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    public function test_auditor_with_expired_access_cannot_read_company_audit_log(): void
    {
        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $company = Company::factory()->create(['company_sector_id' => $sector->id]);

        $auditor = User::factory()->create(['role' => 'auditor']);
        $auditor->companies()->attach($company->id, [
            'role' => 'auditor', 'is_active' => true, 'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($auditor, 'api')
             ->getJson("/api/companies/{$company->id}/audit-logs")
             ->assertStatus(403);
    }

    public function test_auditor_not_assigned_to_company_cannot_read_its_audit_log(): void
    {
        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $company = Company::factory()->create(['company_sector_id' => $sector->id]);
        $outsider = User::factory()->create(['role' => 'auditor']);

        $this->actingAs($outsider, 'api')
             ->getJson("/api/companies/{$company->id}/audit-logs")
             ->assertStatus(403);
    }

    public function test_admin_can_read_audit_log_of_their_own_company_via_company_scoped_endpoint(): void
    {
        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $company = Company::factory()->create(['company_sector_id' => $sector->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->companies()->attach($company->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'api')
             ->getJson("/api/companies/{$company->id}/audit-logs")
             ->assertOk();
    }
}
