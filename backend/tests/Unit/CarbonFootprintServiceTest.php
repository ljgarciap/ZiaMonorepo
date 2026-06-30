<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CarbonFootprintService;
use App\Services\FormulaEvaluationService;
use App\Models\EmissionFactor;
use App\Models\EmissionCategory;
use App\Models\ElectricityFactor;
use App\Models\CalculationFormula;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CarbonFootprintServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Use real services
        $formulaService = new FormulaEvaluationService();
        $this->service = new CarbonFootprintService($formulaService);
    }

    public function test_calculate_gasolina_e10_row16_logic()
    {
        // 1. Setup Factor (Mocking Excel Row 16 attributes + new gases)
        // CO2: 7.618, CH4: 0.0002627, N2O: 0.0000255
        // Uncertainty Upper (CO2): 0.234%
        $factor = EmissionFactor::factory()->create([
            'name' => 'Gasolina E10',
            'factor_co2' => 7.618,
            'factor_ch4' => 0.0002627,
            'factor_n2o' => 0.0000255,
            'factor_nf3' => 0.0000010, // Added for test
            'factor_sf6' => 0.0000020, // Added for test
            'uncertainty_upper' => 0.234, // 0.234%
        ]);

        // 2. Setup Inputs (12 months of 62 gal = 744 total)
        $inputs = array_fill(0, 12, 62);

        // 3. Execute
        $result = $this->service->calculate($inputs, $factor);

        // 4. Verify Emissions
        // CO2: (744 * 7.618)/1000 = 5.667792
        $this->assertEqualsWithDelta(5.667792, $result['emissions_co2'], 0.0001, 'CO2 Emission mismatch');

        // CH4: (744 * 0.0002627)/1000 = 0.0001954488
        $this->assertEqualsWithDelta(0.0001954, $result['emissions_ch4'], 0.000001, 'CH4 Emission mismatch');
        
        // N2O: (744 * 0.0000255)/1000 = 0.000018972
        $this->assertEqualsWithDelta(0.000019, $result['emissions_n2o'], 0.000001, 'N2O Emission mismatch');

        // Total CO2e (AR6 GWP: CH4=29.8, N2O=273, NF3=17400, SF6=25200)
        // CO2e_CO2 = 5.667792 * 1 = 5.667792
        // CO2e_CH4 = 0.000195449 * 29.8 = 0.005824
        // CO2e_N2O = 0.000018972 * 273 = 0.005181
        // CO2e_NF3 = (744*0.0000010/1000) * 17400 = 0.012946
        // CO2e_SF6 = (744*0.0000020/1000) * 25200 = 0.037498
        // Total = 5.667792 + 0.005824 + 0.005181 + 0.012946 + 0.037498 = 5.729241
        $this->assertEqualsWithDelta(5.729241, $result['calculated_co2e'], 0.0001, 'Total CO2e mismatch');

        // 5. Verify Uncertainty (Weights and results shifted due to AR6 GWP)
        // Relative Combined calculated as ~0.2683%
        $this->assertEqualsWithDelta(0.2683, $result['uncertainty_result'], 0.001, 'Uncertainty mismatch');
    }

    public function test_co2_gwp_factor_is_1()
    {
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 1.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([1000], $factor);

        // emissions_co2 = (1000 * 1.0) / 1000 = 1.0
        // co2e = 1.0 * GWP_CO2(1) = 1.0
        $this->assertEqualsWithDelta(1.0, $result['emissions_co2'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $result['calculated_co2e'], 0.0001);
    }

    public function test_ch4_gwp_factor_is_29_8()
    {
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 0.0,
            'factor_ch4'        => 1.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([1000], $factor);

        // emissions_ch4 = (1000 * 1.0) / 1000 = 1.0
        // co2e = 1.0 * GWP_CH4(29.8) = 29.8 [AR6 fossil combustion]
        $this->assertEqualsWithDelta(1.0, $result['emissions_ch4'], 0.0001);
        $this->assertEqualsWithDelta(29.8, $result['calculated_co2e'], 0.0001);
    }

    public function test_n2o_gwp_factor_is_273()
    {
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 0.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 1.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([1000], $factor);

        // emissions_n2o = (1000 * 1.0) / 1000 = 1.0
        // co2e = 1.0 * GWP_N2O(273) = 273.0 [AR6]
        $this->assertEqualsWithDelta(1.0, $result['emissions_n2o'], 0.0001);
        $this->assertEqualsWithDelta(273.0, $result['calculated_co2e'], 0.0001);
    }

    public function test_sf6_gwp_factor_is_25200()
    {
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 0.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 1.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([1000], $factor);

        // emissions_sf6 = (1000 * 1.0) / 1000 = 1.0
        // co2e = 1.0 * GWP_SF6(25200) = 25200.0 [AR6]
        $this->assertEqualsWithDelta(1.0, $result['emissions_sf6'], 0.0001);
        $this->assertEqualsWithDelta(25200.0, $result['calculated_co2e'], 0.0001);
    }

    public function test_nf3_gwp_factor_is_17400()
    {
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 0.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 1.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([1000], $factor);

        // emissions_nf3 = (1000 * 1.0) / 1000 = 1.0
        // co2e = 1.0 * GWP_NF3(17400) = 17400.0 [AR6]
        $this->assertEqualsWithDelta(1.0, $result['emissions_nf3'], 0.0001);
        $this->assertEqualsWithDelta(17400.0, $result['calculated_co2e'], 0.0001);
    }

    public function test_fallback_to_factor_total_co2e_when_no_gwp()
    {
        // When all per-gas factors are 0 and no formula, service falls back to factor_total_co2e
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 0.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 2.5,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([100], $factor);

        // Standard calc gives 0 (all gas factors = 0, no formula assigned)
        // Fallback: (100 * 2.5) / 1000 = 0.25
        $this->assertEqualsWithDelta(0.25, $result['calculated_co2e'], 0.0001);
    }

    public function test_multi_month_sum_is_correct()
    {
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 1.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([100, 200, 300], $factor);

        // Total activity data = 100 + 200 + 300 = 600
        // emissions_co2 = (600 * 1.0) / 1000 = 0.6
        // co2e = 0.6 * GWP_CO2(1) = 0.6
        $this->assertEqualsWithDelta(600.0, $result['activity_data_total'], 0.0001);
        $this->assertEqualsWithDelta(0.6, $result['calculated_co2e'], 0.0001);
    }

    public function test_zero_activity_value_returns_zero_co2e()
    {
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 7.618,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([0], $factor);

        $this->assertEqualsWithDelta(0.0, $result['calculated_co2e'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $result['emissions_co2'], 0.0001);
    }

    public function test_t_table_returns_2_20_for_count_above_12()
    {
        // 13 inputs: 12 × 100 and 1 × 200
        // T_TABLE[min(13,12)] = T_TABLE[12] = 2.20 — NOT 2.0 fallback
        // Exact expected uncertainty: (11/70)*100 = 110/7 ≈ 15.7143%
        // With T=2.0 (wrong): (1/7)*100 ≈ 14.2857% — clearly different
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 1.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $inputs = array_merge(array_fill(0, 12, 100.0), [200.0]); // 13 values

        $result = $this->service->calculate($inputs, $factor);

        $this->assertEqualsWithDelta(
            15.7143,
            $result['uncertainty_result'],
            0.001,
            'T-table must use T=2.20 for N=13: min(13,12)=12, T_TABLE[12]=2.20'
        );
    }

    public function test_uncertainty_is_zero_for_single_month()
    {
        // Single data point → activityDataUncertainty = 0.0
        // With u_fact_co2 = 0 → combined uncertainty = 0%
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 7.618,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([500], $factor);

        $this->assertEqualsWithDelta(0.0, $result['uncertainty_result'], 0.0001);
    }

    // ─── seeder regression values ─────────────────────────────────────────────

    public function test_gas_natural_seeder_value()
    {
        // Gas Natural: 1.933 kgCO2/m3 (from EmissionFactorSeeder — Colombia UPME)
        // 1000 m3 of natural gas → 1.933 tCO2
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 1.933,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([1000], $factor);

        // (1000 * 1.933) / 1000 = 1.933 tCO2
        $this->assertEqualsWithDelta(1.933, $result['emissions_co2'], 0.0001);
        $this->assertEqualsWithDelta(1.933, $result['calculated_co2e'], 0.0001);
    }

    public function test_refrigerant_r410a_seeder_value()
    {
        // R-410A (HFC): GWP 2088 kgCO2e/kg — stored as factor_total_co2e in seeder
        // 0.5 kg leaked → fallback: (0.5 * 2088) / 1000 = 1.044 tCO2e
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 0.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 2088.0,
            'uncertainty_upper' => 0.0,
        ]);

        $result = $this->service->calculate([0.5], $factor);

        $this->assertEqualsWithDelta(1.044, $result['calculated_co2e'], 0.001);
        $this->assertEqualsWithDelta(0.5,   $result['activity_data_total'], 0.0001);
    }

    // ─── is_biogenic flag (Sprint 8) ─────────────────────────────────────────

    public function test_biogenic_co2_excluded_from_total_but_reported_separately(): void
    {
        // Per GHG Protocol: biogenic CO2 does NOT count toward the GEI total.
        // CH4 and N2O from biogenic combustion DO count.
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 2.0,
            'factor_ch4'        => 0.1,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
            'is_biogenic'       => true,
            'is_removal'        => false,
        ]);

        $result = $this->service->calculate([1000], $factor);

        // emissions_co2 = (1000 * 2.0) / 1000 = 2.0 tCO2
        // emissions_ch4 = (1000 * 0.1)  / 1000 = 0.1 tCH4
        // co2e_CO2 = 2.0 * 1     = 2.0 (biogenic → goes to biogenic_co2e, NOT calculated_co2e)
        // co2e_CH4 = 0.1 * 29.8  = 2.98
        // calculated_co2e = 2.98 (CH4 only, CO2 excluded)
        // biogenic_co2e   = 2.0
        $this->assertEqualsWithDelta(2.98, $result['calculated_co2e'], 0.001);
        $this->assertEqualsWithDelta(2.0,  $result['biogenic_co2e'],   0.001);
    }

    public function test_non_biogenic_factor_has_zero_biogenic_co2e(): void
    {
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 1.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
            'is_biogenic'       => false,
            'is_removal'        => false,
        ]);

        $result = $this->service->calculate([1000], $factor);

        $this->assertEqualsWithDelta(1.0, $result['calculated_co2e'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['biogenic_co2e'],   0.001);
    }

    // ─── is_removal flag (Sprint 8) ──────────────────────────────────────────

    public function test_removal_factor_produces_negative_co2e(): void
    {
        // Carbon removal sources (reforestation, soil sequestration) produce negative CO2e.
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 1.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
            'is_biogenic'       => false,
            'is_removal'        => true,
        ]);

        $result = $this->service->calculate([1000], $factor);

        // Standard: (1000 * 1.0) / 1000 * 1 = 1.0 tCO2e
        // is_removal → negate → -1.0 tCO2e
        $this->assertEqualsWithDelta(-1.0, $result['calculated_co2e'], 0.001);
    }

    public function test_removal_flag_returns_result_key_in_response(): void
    {
        $factor = EmissionFactor::factory()->create([
            'factor_co2'        => 2.0,
            'factor_ch4'        => 0.0,
            'factor_n2o'        => 0.0,
            'factor_nf3'        => 0.0,
            'factor_sf6'        => 0.0,
            'factor_total_co2e' => 0.0,
            'uncertainty_upper' => 0.0,
            'is_removal'        => true,
        ]);

        $result = $this->service->calculate([500], $factor);

        $this->assertArrayHasKey('biogenic_co2e', $result);
        $this->assertLessThan(0, $result['calculated_co2e']);
    }

    // ─── FECOC year-lookup (T1) ──────────────────────────────────────────────

    public function test_electricity_factor_uses_fecoc_for_year(): void
    {
        $scope2 = Scope::firstOrCreate(['name' => 'Alcance 2'], ['number' => 2, 'description' => 'Electricidad']);
        $category = EmissionCategory::create(['name' => 'Electricidad Red', 'scope_id' => $scope2->id]);

        ElectricityFactor::create(['year' => 2024, 'region_code' => 'CO', 'value_kgco2e' => 0.1083, 'source' => 'FECOC']);

        $factor = EmissionFactor::factory()->create([
            'name'                 => 'FE Colombia (Interconectado)',
            'emission_category_id' => $category->id,
            'factor_co2'           => 0.9999, // should be overridden by FECOC
            'factor_ch4'           => 0.0,
            'factor_n2o'           => 0.0,
            'factor_nf3'           => 0.0,
            'factor_sf6'           => 0.0,
            'factor_total_co2e'    => 0.0,
            'uncertainty_upper'    => 0.0,
        ]);
        $factor->load('category.scope');

        // 1 000 kWh × 0.1083 kgCO2e/kWh / 1 000 = 0.1083 tCO2e
        $result = $this->service->calculate([1000], $factor, 2024);

        $this->assertEqualsWithDelta(0.1083, $result['calculated_co2e'], 0.0001);
    }

    public function test_non_electricity_factor_ignores_fecoc(): void
    {
        // Scope 1 factor — FECOC lookup must NOT fire
        $scope1 = Scope::firstOrCreate(['name' => 'Alcance 1'], ['number' => 1, 'description' => 'Combustión']);
        $category = EmissionCategory::create(['name' => 'Fuentes Fijas', 'scope_id' => $scope1->id]);

        ElectricityFactor::create(['year' => 2024, 'region_code' => 'CO', 'value_kgco2e' => 0.1083, 'source' => 'FECOC']);

        $factor = EmissionFactor::factory()->create([
            'name'                 => 'Gas Natural',
            'emission_category_id' => $category->id,
            'factor_co2'           => 2.0,
            'factor_ch4'           => 0.0,
            'factor_n2o'           => 0.0,
            'factor_nf3'           => 0.0,
            'factor_sf6'           => 0.0,
            'factor_total_co2e'    => 0.0,
            'uncertainty_upper'    => 0.0,
        ]);
        $factor->load('category.scope');

        $result = $this->service->calculate([1000], $factor, 2024);

        // (1 000 × 2.0) / 1 000 = 2.0 — original value, not FECOC
        $this->assertEqualsWithDelta(2.0, $result['calculated_co2e'], 0.0001);
    }

    public function test_electricity_factor_falls_back_when_no_fecoc_for_year(): void
    {
        $scope2 = Scope::firstOrCreate(['name' => 'Alcance 2'], ['number' => 2, 'description' => 'Electricidad']);
        $category = EmissionCategory::create(['name' => 'Electricidad Red', 'scope_id' => $scope2->id]);

        // No FECOC record for year 2099

        $factor = EmissionFactor::factory()->create([
            'name'                 => 'FE Colombia (Interconectado)',
            'emission_category_id' => $category->id,
            'factor_co2'           => 0.1260, // original seeded value
            'factor_ch4'           => 0.0,
            'factor_n2o'           => 0.0,
            'factor_nf3'           => 0.0,
            'factor_sf6'           => 0.0,
            'factor_total_co2e'    => 0.0,
            'uncertainty_upper'    => 0.0,
        ]);
        $factor->load('category.scope');

        $result = $this->service->calculate([1000], $factor, 2099);

        // Falls back to original factor_co2 = 0.1260
        $this->assertEqualsWithDelta(0.1260, $result['calculated_co2e'], 0.0001);
    }

    // ─── custom formula branch (P0.3 regression) ─────────────────────────────

    public function test_custom_formula_overrides_standard_gwp_sum()
    {
        // Factor has CH4 that would add 70 tCO2e via standard GWP sum.
        // The formula ignores CH4 entirely — total should be 5, not 75.
        $formula = CalculationFormula::create([
            'name'       => 'CO2-only custom',
            'expression' => '(activity_data * factor_co2) / 1000',
        ]);

        $factor = EmissionFactor::factory()->create([
            'factor_co2'              => 10.0,
            'factor_ch4'              => 5.0,
            'factor_n2o'              => 0.0,
            'factor_nf3'              => 0.0,
            'factor_sf6'              => 0.0,
            'factor_total_co2e'       => 0.0,
            'uncertainty_upper'       => 0.0,
            'calculation_formula_id'  => $formula->id,
        ]);

        $result = $this->service->calculate([500], $factor);

        // Formula result: (500 * 10) / 1000 = 5.0
        // Standard without formula would be: 5.0 + (2.5 * 28) = 75.0
        $this->assertEqualsWithDelta(5.0, $result['calculated_co2e'], 0.0001);

        // Per-gas breakdown is still computed (formula only overrides total)
        $this->assertEqualsWithDelta(2.5, $result['emissions_ch4'], 0.0001);
    }
}
