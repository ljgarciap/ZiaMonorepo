<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;

class AdminCompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_sees_all_companies()
    {
        Company::factory()->count(3)->create();

        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($superadmin, 'api');

        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_admin_sees_only_their_own_companies()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $admin = User::factory()->create(['role' => 'admin']);
        // Attach only company1 to the admin via pivot
        $admin->companies()->attach($company1->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'api');

        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(200);
        // Admin should only see their own company
        $this->assertCount(1, $response->json());
        $this->assertEquals($company1->id, $response->json()[0]['id']);
    }

    public function test_unauthenticated_returns_401()
    {
        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(401);
    }

    public function test_non_admin_user_returns_403()
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $this->actingAs($regularUser, 'api');

        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(403);
    }

    // ─── approveMethodology (gap: "aprobar estructuras metodológicas") ────────

    public function test_superadmin_can_approve_methodology()
    {
        $company = Company::factory()->create(['methodology' => 'GHG_PROTOCOL']);
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin, 'api')
             ->postJson("/api/admin/companies/{$company->id}/approve-methodology");

        $response->assertOk()
                 ->assertJsonPath('is_methodology_approved', true)
                 ->assertJsonPath('methodology_approved_by', $superadmin->id);

        $company->refresh();
        $this->assertTrue($company->is_methodology_approved);
        $this->assertNotNull($company->methodology_approved_at);
    }

    public function test_admin_cannot_approve_methodology()
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->companies()->attach($company->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'api')
             ->postJson("/api/admin/companies/{$company->id}/approve-methodology")
             ->assertStatus(403);
    }

    public function test_changing_methodology_fields_revokes_prior_approval()
    {
        $company = Company::factory()->create(['methodology' => 'GHG_PROTOCOL']);
        $company->update([
            'is_methodology_approved' => true,
            'methodology_approved_at' => now(),
            'methodology_approved_by' => User::factory()->create(['role' => 'superadmin'])->id,
        ]);

        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin, 'api')
             ->putJson("/api/admin/companies/{$company->id}", ['methodology' => 'ISO_14064'])
             ->assertOk()
             ->assertJsonPath('is_methodology_approved', false);

        $company->refresh();
        $this->assertFalse($company->is_methodology_approved);
        $this->assertNull($company->methodology_approved_at);
    }

    public function test_updating_unrelated_field_does_not_revoke_approval()
    {
        $company = Company::factory()->create();
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $company->update([
            'is_methodology_approved' => true,
            'methodology_approved_at' => now(),
            'methodology_approved_by' => $superadmin->id,
        ]);

        $this->actingAs($superadmin, 'api')
             ->putJson("/api/admin/companies/{$company->id}", ['contact_email' => 'nuevo@empresa.co'])
             ->assertOk()
             ->assertJsonPath('is_methodology_approved', true);
    }
}
