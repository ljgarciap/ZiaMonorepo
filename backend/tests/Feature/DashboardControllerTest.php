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
}
