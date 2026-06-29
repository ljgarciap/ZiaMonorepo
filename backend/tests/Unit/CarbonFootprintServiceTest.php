<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CarbonFootprintService;
use App\Services\FormulaEvaluationService;
use App\Models\EmissionFactor;
use App\Models\CalculationFormula;
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

        // Total CO2e (Using new GWP: CH4=28, N2O=265, NF3=16100, SF6=23500)
        // CO2e_CO2 = 5.667792 * 1 = 5.667792
        // CO2e_CH4 = 0.0001954488 * 28 = 0.005472566
        // CO2e_N2O = 0.000018972 * 265 = 0.00502758
        // CO2e_NF3 = (744*0.0000010/1000) * 16100 = 0.0119784
        // CO2e_SF6 = (744*0.0000020/1000) * 23500 = 0.034968
        // Total = 5.667792 + 0.005472566 + 0.00502758 + 0.0119784 + 0.034968 = 5.725238...
        $this->assertEqualsWithDelta(5.725238, $result['calculated_co2e'], 0.0001, 'Total CO2e mismatch');

        // 5. Verify Uncertainty (Weights and results slightly shifted due to GWP)
        // Relative Combined calculated as ~0.2643%
        $this->assertEqualsWithDelta(0.2643, $result['uncertainty_result'], 0.001, 'Uncertainty mismatch');
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

    public function test_ch4_gwp_factor_is_28()
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
        // co2e = 1.0 * GWP_CH4(28) = 28.0
        $this->assertEqualsWithDelta(1.0, $result['emissions_ch4'], 0.0001);
        $this->assertEqualsWithDelta(28.0, $result['calculated_co2e'], 0.0001);
    }

    public function test_n2o_gwp_factor_is_265()
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
        // co2e = 1.0 * GWP_N2O(265) = 265.0
        $this->assertEqualsWithDelta(1.0, $result['emissions_n2o'], 0.0001);
        $this->assertEqualsWithDelta(265.0, $result['calculated_co2e'], 0.0001);
    }

    public function test_sf6_gwp_factor_is_23500()
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
        // co2e = 1.0 * GWP_SF6(23500) = 23500.0
        $this->assertEqualsWithDelta(1.0, $result['emissions_sf6'], 0.0001);
        $this->assertEqualsWithDelta(23500.0, $result['calculated_co2e'], 0.0001);
    }

    public function test_nf3_gwp_factor_is_16100()
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
        // co2e = 1.0 * GWP_NF3(16100) = 16100.0
        $this->assertEqualsWithDelta(1.0, $result['emissions_nf3'], 0.0001);
        $this->assertEqualsWithDelta(16100.0, $result['calculated_co2e'], 0.0001);
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
