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
}
