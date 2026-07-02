<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_spoof_superadmin_via_context_role_header(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'api')
             ->withHeaders(['X-Context-Role' => 'superadmin'])
             ->getJson('/api/admin/companies')
             ->assertStatus(403);
    }

    public function test_admin_cannot_spoof_superadmin_via_context_role_header(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'api')
             ->withHeaders(['X-Context-Role' => 'superadmin'])
             ->postJson('/api/admin/companies', [])
             ->assertStatus(403);
    }

    public function test_superadmin_can_self_downgrade_to_admin_context(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin, 'api')
             ->withHeaders(['X-Context-Role' => 'admin'])
             ->getJson('/api/admin/companies')
             ->assertOk();
    }

    public function test_user_cannot_claim_admin_role_of_company_they_do_not_belong_to(): void
    {
        $sector  = CompanySector::create(['name' => 'Servicios Test']);
        $company = Company::factory()->create(['company_sector_id' => $sector->id]);
        $user    = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'api')
             ->withHeaders([
                 'X-Context-Role' => 'admin',
                 'X-Company-ID' => $company->id,
             ])
             ->getJson('/api/admin/companies')
             ->assertStatus(403);
    }

    public function test_user_can_claim_admin_role_of_company_they_actually_manage(): void
    {
        $sector  = CompanySector::create(['name' => 'Servicios Test']);
        $company = Company::factory()->create(['company_sector_id' => $sector->id]);
        $user    = User::factory()->create(['role' => 'user']);
        $user->companies()->attach($company->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user, 'api')
             ->withHeaders([
                 'X-Context-Role' => 'admin',
                 'X-Company-ID' => $company->id,
             ])
             ->getJson('/api/admin/companies')
             ->assertOk();
    }

    public function test_expired_auditor_access_is_denied_even_though_base_role_still_matches(): void
    {
        $sector  = CompanySector::create(['name' => 'Servicios Test']);
        $company = Company::factory()->create(['company_sector_id' => $sector->id]);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $auditor->companies()->attach($company->id, [
            'role' => 'auditor',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($auditor, 'api')
             ->withHeaders([
                 'X-Context-Role' => 'auditor',
                 'X-Company-ID' => $company->id,
             ])
             ->getJson("/api/companies/{$company->id}/emissions/history")
             ->assertStatus(403);
    }

    public function test_active_auditor_access_is_allowed_within_expiration(): void
    {
        $sector  = CompanySector::create(['name' => 'Servicios Test']);
        $company = Company::factory()->create(['company_sector_id' => $sector->id]);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $auditor->companies()->attach($company->id, [
            'role' => 'auditor',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($auditor, 'api')
             ->withHeaders([
                 'X-Context-Role' => 'auditor',
                 'X-Company-ID' => $company->id,
             ])
             ->getJson("/api/companies/{$company->id}/emissions/history")
             ->assertStatus(200);
    }
}
