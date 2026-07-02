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
}
