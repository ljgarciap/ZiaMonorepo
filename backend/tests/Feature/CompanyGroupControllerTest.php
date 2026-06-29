<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\Period;
use App\Models\EmissionFactor;
use App\Models\EmissionCategory;
use App\Models\Scope;
use App\Models\CarbonEmission;

class CompanyGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin  = User::factory()->create(['role' => 'superadmin']);
        $this->admin       = User::factory()->create(['role' => 'admin']);
        $this->regularUser = User::factory()->create(['role' => 'user']);
    }

    // ─── access control ──────────────────────────────────────────────────────

    public function test_admin_cannot_access_group_endpoints()
    {
        $this->actingAs($this->admin, 'api')
             ->getJson('/api/admin/groups')
             ->assertForbidden();
    }

    public function test_regular_user_cannot_access_group_endpoints()
    {
        $this->actingAs($this->regularUser, 'api')
             ->getJson('/api/admin/groups')
             ->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/admin/groups')
             ->assertUnauthorized();
    }

    // ─── CRUD ────────────────────────────────────────────────────────────────

    public function test_superadmin_can_list_groups()
    {
        CompanyGroup::create(['name' => 'Edificio UDES', 'created_by' => $this->superadmin->id]);
        CompanyGroup::create(['name' => 'Consorcio Norte', 'created_by' => $this->superadmin->id]);

        $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/groups')
             ->assertOk()
             ->assertJsonCount(2);
    }

    public function test_superadmin_can_create_group_with_companies()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/groups', [
                 'name'        => 'Edificio Parque Tecnológico',
                 'description' => 'UDES + IMEBU comparten edificio',
                 'company_ids' => [$company1->id, $company2->id],
             ]);

        $response->assertCreated()
                 ->assertJsonPath('name', 'Edificio Parque Tecnológico');

        $group = CompanyGroup::first();
        $this->assertCount(2, $group->companies);
        $this->assertEquals($this->superadmin->id, $group->created_by);
    }

    public function test_superadmin_can_create_group_without_companies()
    {
        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/groups', ['name' => 'Grupo Vacío'])
             ->assertCreated()
             ->assertJsonPath('name', 'Grupo Vacío');

        $this->assertCount(0, CompanyGroup::first()->companies);
    }

    public function test_create_group_validates_required_name()
    {
        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/groups', ['description' => 'Sin nombre'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['name']);
    }

    public function test_superadmin_can_add_company_to_existing_group()
    {
        $group   = CompanyGroup::create(['name' => 'Test Group', 'created_by' => $this->superadmin->id]);
        $company = Company::factory()->create();

        $this->actingAs($this->superadmin, 'api')
             ->postJson("/api/admin/groups/{$group->id}/companies", ['company_id' => $company->id])
             ->assertOk()
             ->assertJsonFragment(['message' => 'Company added to group']);

        $this->assertCount(1, $group->fresh()->companies);
    }

    public function test_add_company_is_idempotent()
    {
        $group   = CompanyGroup::create(['name' => 'Test', 'created_by' => $this->superadmin->id]);
        $company = Company::factory()->create();
        $group->companies()->attach($company->id);

        // Adding the same company again should not duplicate
        $this->actingAs($this->superadmin, 'api')
             ->postJson("/api/admin/groups/{$group->id}/companies", ['company_id' => $company->id])
             ->assertOk();

        $this->assertCount(1, $group->fresh()->companies);
    }

    public function test_superadmin_can_remove_company_from_group()
    {
        $group   = CompanyGroup::create(['name' => 'Test', 'created_by' => $this->superadmin->id]);
        $company = Company::factory()->create();
        $group->companies()->attach($company->id);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/groups/{$group->id}/companies", ['company_id' => $company->id])
             ->assertOk()
             ->assertJsonFragment(['message' => 'Company removed from group']);

        $this->assertCount(0, $group->fresh()->companies);
    }

    public function test_superadmin_can_soft_delete_group()
    {
        $group = CompanyGroup::create(['name' => 'Para borrar', 'created_by' => $this->superadmin->id]);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/groups/{$group->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('company_groups', ['id' => $group->id]);
    }

    // ─── aggregate summary ───────────────────────────────────────────────────

    private function buildGroupWithEmissions(float $co2eA, float $co2eB, int $year = 2025): CompanyGroup
    {
        $scope    = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Direct']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor   = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);

        $companyA = Company::factory()->create();
        $periodA  = Period::factory()->create(['company_id' => $companyA->id, 'year' => $year]);
        CarbonEmission::factory()->create([
            'period_id'          => $periodA->id,
            'emission_factor_id' => $factor->id,
            'calculated_co2e'    => $co2eA,
        ]);

        $companyB = Company::factory()->create();
        $periodB  = Period::factory()->create(['company_id' => $companyB->id, 'year' => $year]);
        CarbonEmission::factory()->create([
            'period_id'          => $periodB->id,
            'emission_factor_id' => $factor->id,
            'calculated_co2e'    => $co2eB,
        ]);

        $group = CompanyGroup::create([
            'name'       => 'Edificio Test',
            'created_by' => $this->superadmin->id,
        ]);
        $group->companies()->attach([$companyA->id, $companyB->id]);

        return $group;
    }

    public function test_summary_aggregates_emissions_across_group_companies()
    {
        $group = $this->buildGroupWithEmissions(30.0, 70.0, 2025);

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson("/api/admin/groups/{$group->id}/summary?year=2025");

        $response->assertOk()
                 ->assertJsonPath('group.id', $group->id)
                 ->assertJsonPath('year', '2025');

        $total = $response->json('total_co2e');
        $this->assertEqualsWithDelta(100.0, $total, 0.01);

        $byCompany = $response->json('by_company');
        $this->assertCount(2, $byCompany);
    }

    public function test_summary_returns_breakdown_by_scope()
    {
        $group = $this->buildGroupWithEmissions(40.0, 60.0, 2025);

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson("/api/admin/groups/{$group->id}/summary?year=2025")
             ->assertOk();

        $byScope = $response->json('by_scope');
        $this->assertNotEmpty($byScope);
        $scopeTotal = collect($byScope)->sum('total_co2e');
        $this->assertEqualsWithDelta(100.0, $scopeTotal, 0.01);
    }

    public function test_summary_without_year_returns_all_periods()
    {
        $group = $this->buildGroupWithEmissions(50.0, 50.0, 2024);

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson("/api/admin/groups/{$group->id}/summary")
             ->assertOk();

        $this->assertNull($response->json('year'));
        $this->assertEqualsWithDelta(100.0, $response->json('total_co2e'), 0.01);
    }

    public function test_summary_returns_zero_when_group_has_no_emissions()
    {
        $group = CompanyGroup::create(['name' => 'Vacío', 'created_by' => $this->superadmin->id]);

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson("/api/admin/groups/{$group->id}/summary?year=2025")
             ->assertOk();

        $this->assertEquals(0, $response->json('total_co2e'));
        $this->assertEmpty($response->json('by_company'));
    }
}
