<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Period;
use App\Models\Scope;
use App\Models\EmissionCategory;
use App\Models\EmissionFactor;
use App\Models\CarbonEmission;
use App\Models\AuditorAssignment;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User    $user;
    protected Company $company;
    protected Period  $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create(['role' => 'admin']);
        $this->company = Company::factory()->create();
        $this->period  = Period::factory()->create(['company_id' => $this->company->id]);
        $this->user->companies()->attach($this->company->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($this->user, 'api');
    }

    public function test_summary_returns_scope_breakdown()
    {
        // Create one emission in scope 1
        $scope    = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor   = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);

        CarbonEmission::factory()->create([
            'period_id'          => $this->period->id,
            'emission_factor_id' => $factor->id,
            'calculated_co2e'    => 10.0,
        ]);

        $response = $this->getJson(
            "/api/dashboard/summary?company_id={$this->company->id}&period_id={$this->period->id}"
        );

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'huella_total',
                     'alcances' => [
                         'scope_1' => ['total', 'percentage', 'neutralizado'],
                         'scope_2' => ['total', 'percentage', 'neutralizado'],
                         'scope_3' => ['total', 'percentage', 'neutralizado'],
                     ],
                 ]);

        $this->assertEqualsWithDelta(10.0, $response->json('huella_total'), 0.01);
    }

    public function test_summary_handles_period_with_no_emissions()
    {
        $response = $this->getJson(
            "/api/dashboard/summary?company_id={$this->company->id}&period_id={$this->period->id}"
        );

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(0.0, $response->json('huella_total'), 0.001);
    }

    public function test_scope_percentages_sum_to_approximately_100()
    {
        // Two scopes with equal emissions (50 tCO2e each) → each 50%, total 100%
        $scope1 = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $scope2 = Scope::firstOrCreate(['name' => 'Alcance 2'], ['description' => 'Scope 2']);

        $cat1 = EmissionCategory::factory()->create(['scope_id' => $scope1->id]);
        $cat2 = EmissionCategory::factory()->create(['scope_id' => $scope2->id]);

        $factor1 = EmissionFactor::factory()->create(['emission_category_id' => $cat1->id]);
        $factor2 = EmissionFactor::factory()->create(['emission_category_id' => $cat2->id]);

        CarbonEmission::factory()->create([
            'period_id'          => $this->period->id,
            'emission_factor_id' => $factor1->id,
            'calculated_co2e'    => 50.0,
        ]);
        CarbonEmission::factory()->create([
            'period_id'          => $this->period->id,
            'emission_factor_id' => $factor2->id,
            'calculated_co2e'    => 50.0,
        ]);

        $response = $this->getJson(
            "/api/dashboard/summary?company_id={$this->company->id}&period_id={$this->period->id}"
        );

        $response->assertStatus(200);

        $sumPercentages = $response->json('alcances.scope_1.percentage')
            + $response->json('alcances.scope_2.percentage')
            + $response->json('alcances.scope_3.percentage');

        $this->assertEqualsWithDelta(100, $sumPercentages, 1.0,
            'Sum of all scope percentages must be approximately 100');
    }

    public function test_summary_requires_company_id_and_period_id()
    {
        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(400)
                 ->assertJsonPath('error', 'Company and Period are required');
    }

    public function test_summary_returns_intensity_kpis_when_company_has_dimensions()
    {
        $company = Company::factory()->create([
            'floor_sqm'     => 100,
            'num_employees' => 10,
        ]);
        $period = Period::factory()->create(['company_id' => $company->id]);
        $this->user->companies()->attach($company->id, ['role' => 'admin', 'is_active' => true]);

        $scope    = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor   = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);

        CarbonEmission::factory()->create([
            'period_id'          => $period->id,
            'emission_factor_id' => $factor->id,
            'calculated_co2e'    => 10.0,
        ]);

        $response = $this->getJson(
            "/api/dashboard/summary?company_id={$company->id}&period_id={$period->id}"
        );

        $response->assertStatus(200)
                 ->assertJsonStructure(['intensidad_kpis' => ['tco2e_por_m2', 'tco2e_por_empleado']]);

        $this->assertEqualsWithDelta(0.1,  $response->json('intensidad_kpis.tco2e_por_m2'),       0.001);
        $this->assertEqualsWithDelta(1.0,  $response->json('intensidad_kpis.tco2e_por_empleado'),  0.001);
    }

    public function test_summary_intensity_kpis_null_when_company_has_no_dimensions()
    {
        $company = Company::factory()->create(['floor_sqm' => null, 'num_employees' => null]);
        $period  = Period::factory()->create(['company_id' => $company->id]);
        $this->user->companies()->attach($company->id, ['role' => 'admin', 'is_active' => true]);

        $response = $this->getJson(
            "/api/dashboard/summary?company_id={$company->id}&period_id={$period->id}"
        );

        $response->assertStatus(200);
        $this->assertNull($response->json('intensidad_kpis.tco2e_por_m2'));
        $this->assertNull($response->json('intensidad_kpis.tco2e_por_empleado'));
    }

    public function test_unauthenticated_request_returns_401()
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(
            "/api/dashboard/summary?company_id={$this->company->id}&period_id={$this->period->id}"
        );

        $response->assertStatus(401);
    }

    // ─── scope='own' para rol Usuario (matriz CRUD: Dashboard = solo métricas propias) ──

    public function test_user_role_only_sees_own_emissions_in_summary()
    {
        $scope    = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor   = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);

        $operativeUser = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $operativeUser->companies()->attach($this->company->id, ['role' => 'user', 'is_active' => true]);

        CarbonEmission::factory()->create([
            'period_id' => $this->period->id, 'emission_factor_id' => $factor->id,
            'calculated_co2e' => 10.0, 'user_id' => $operativeUser->id,
        ]);
        CarbonEmission::factory()->create([
            'period_id' => $this->period->id, 'emission_factor_id' => $factor->id,
            'calculated_co2e' => 90.0, 'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($operativeUser, 'api')->getJson(
            "/api/dashboard/summary?company_id={$this->company->id}&period_id={$this->period->id}"
        );

        $response->assertOk()->assertJsonPath('scope', 'own');
        $this->assertEqualsWithDelta(10.0, $response->json('huella_total'), 0.01);
    }

    public function test_admin_role_sees_company_wide_emissions_in_summary()
    {
        $scope    = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor   = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);

        CarbonEmission::factory()->create([
            'period_id' => $this->period->id, 'emission_factor_id' => $factor->id, 'calculated_co2e' => 10.0,
        ]);
        CarbonEmission::factory()->create([
            'period_id' => $this->period->id, 'emission_factor_id' => $factor->id, 'calculated_co2e' => 90.0,
        ]);

        $response = $this->getJson(
            "/api/dashboard/summary?company_id={$this->company->id}&period_id={$this->period->id}"
        );

        $response->assertOk()->assertJsonPath('scope', 'company');
        $this->assertEqualsWithDelta(100.0, $response->json('huella_total'), 0.01);
    }

    public function test_intensity_kpis_are_null_for_own_scope()
    {
        $company = Company::factory()->create(['floor_sqm' => 100, 'num_employees' => 10]);
        $period  = Period::factory()->create(['company_id' => $company->id]);
        $operativeUser = User::factory()->create(['role' => 'user']);
        $operativeUser->companies()->attach($company->id, ['role' => 'user', 'is_active' => true]);

        $response = $this->actingAs($operativeUser, 'api')->getJson(
            "/api/dashboard/summary?company_id={$company->id}&period_id={$period->id}"
        );

        $response->assertOk();
        $this->assertNull($response->json('intensidad_kpis'));
    }

    public function test_trends_only_counts_own_emissions_for_user_role()
    {
        $scope    = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor   = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);

        $operativeUser = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $operativeUser->companies()->attach($this->company->id, ['role' => 'user', 'is_active' => true]);

        CarbonEmission::factory()->create([
            'period_id' => $this->period->id, 'emission_factor_id' => $factor->id,
            'calculated_co2e' => 5.0, 'user_id' => $operativeUser->id,
        ]);
        CarbonEmission::factory()->create([
            'period_id' => $this->period->id, 'emission_factor_id' => $factor->id,
            'calculated_co2e' => 95.0, 'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($operativeUser, 'api')
             ->getJson("/api/dashboard/trends?company_id={$this->company->id}");

        $response->assertOk();
        $this->assertEqualsWithDelta(5.0, $response->json('revenue_trend.datasets.0.data.0'), 0.01);
    }

    // ─── trends(): auditor solo ve los períodos que audita, no todo el histórico ──

    public function test_trends_only_includes_periods_assigned_to_auditor()
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        // Períodos previos NO asignados al auditor
        $olderPeriod = Period::factory()->create(['company_id' => $this->company->id, 'year' => $this->period->year - 2]);
        $middlePeriod = Period::factory()->create(['company_id' => $this->company->id, 'year' => $this->period->year - 1]);

        // Solo tiene asignación activa para $this->period
        AuditorAssignment::factory()->create([
            'user_id'    => $auditor->id,
            'company_id' => $this->company->id,
            'period_id'  => $this->period->id,
            'expires_at' => null,
        ]);

        $scope    = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor   = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);

        foreach ([$olderPeriod, $middlePeriod, $this->period] as $period) {
            CarbonEmission::factory()->create([
                'period_id' => $period->id, 'emission_factor_id' => $factor->id, 'calculated_co2e' => 10.0,
            ]);
        }

        $response = $this->actingAs($auditor, 'api')
             ->getJson("/api/dashboard/trends?company_id={$this->company->id}");

        $response->assertOk();
        $labels = $response->json('revenue_trend.labels');
        $this->assertCount(1, $labels);
        $this->assertEquals((string) $this->period->year, $labels[0]);
    }

    public function test_trends_returns_all_periods_for_auditor_with_multiple_assignments()
    {
        $auditor = User::factory()->create(['role' => 'auditor']);
        $secondPeriod = Period::factory()->create(['company_id' => $this->company->id, 'year' => $this->period->year - 1]);

        foreach ([$this->period, $secondPeriod] as $period) {
            AuditorAssignment::factory()->create([
                'user_id'    => $auditor->id,
                'company_id' => $this->company->id,
                'period_id'  => $period->id,
                'expires_at' => null,
            ]);
        }

        $response = $this->actingAs($auditor, 'api')
             ->getJson("/api/dashboard/trends?company_id={$this->company->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('revenue_trend.labels'));
    }
}
