<?php

namespace App\Services;

use App\Models\EmissionFactor;
use App\Models\CalculationFormula;
use Exception;

class CarbonFootprintService
{
    protected $formulaService;

    // GWP Values from Excel (AR6 likely)
    const GWP_CO2 = 1.0;
    const GWP_CH4 = 28.0;
    const GWP_N2O = 265.0;
    const GWP_NF3 = 16100.0;
    const GWP_SF6 = 23500.0;

    // T-Distribution Table (2-tailed, 95% confidence?, based on Excel extraction)
    // Key: Count (Sample Size), Value: T-Factor
    const T_TABLE = [
        2 => 12.71,
        3 => 4.30,
        4 => 3.18,
        5 => 2.78,
        6 => 2.57,
        7 => 2.45,
        8 => 2.36,
        9 => 2.31,
        10 => 2.26,
        11 => 2.23,
        12 => 2.20,
        // Extrapolate or use 12+ as 2.0? Excel stopped at 12?
        // Let's assume >12 uses 1.96 (Z-score for infinite df) or similar, but stick to Excel max for now.
    ];

    public function __construct(FormulaEvaluationService $formulaService)
    {
        $this->formulaService = $formulaService;
    }

    /**
     * Calculate emissions and uncertainty for a set of inputs.
     * 
     * @param array $inputs Array of monthly values (activity data).
     * @param EmissionFactor $factor The emission factor to apply.
     * @return array Result with detailed breakdowns.
     */
    public function calculate(array $inputs, EmissionFactor $factor): array
    {
        // 1. Activity Data Statistics
        $count = count($inputs);
        $totalActivityData = array_sum($inputs);
        
        $activityDataUncertainty = 0.0;
        $stdev = 0.0;
        $mean = 0.0;

        if ($count > 1 && $totalActivityData > 0) {
            $mean = $totalActivityData / $count;
            $stdev = self::calculateStdev($inputs, $mean);
            
            // Uncertainty Calculation: 1 - ( (Mean - ( (Stdev * T) / Sqrt(N) ) ) / Mean )
            // Effectively: (Stdev * T) / (Mean * Sqrt(N)) -> Relative Uncertainty (Half Width / Mean)
            // Excel Formula: 1 - ((S - ((T*U)/(SQRT(R))))/S)
            // = 1 - ( 1 - (T*U)/(S*Avg(R)) ) = (T*U)/(S*Avg(R)) ??
            // Let's trace Excel: 1 - ( (Mean - Error) / Mean ) = 1 - (1 - Error/Mean) = Error/Mean.
            // Yes, it is Relative Error.
            
            $tFactor = self::T_TABLE[min($count, 12)] ?? 2.0; // Fallback
            $standardError = ($stdev * $tFactor) / sqrt($count);
            $activityDataUncertainty = $standardError / $mean; // Relative, e.g. 0.05 for 5%
        } else {
            // If only 1 data point, uncertainty is unknown from data. 
            // Usually valid to take 0 or a default.
            $activityDataUncertainty = 0.0;
        }

        // 2. Emission Calculations (Mass)
        // Formula context - simplest case: Total * Factor
        // But if we use dynamic formulas, we fetch expression.
        // Assuming default expression for simple factors is: `activity_data * factor`
        
        $formula = $factor->formula;
        $emissionsCO2 = 0;
        $emissionsCH4 = 0;
        $emissionsN2O = 0;
        
        // Convert Activity Data to correct unit if needed? 
        // Excel: (Sum * Factor) / 1000. 
        // Factor is usually kg/unit, we want Tonnes.
        
        // We will calculate Mass of Gas first (kg or tonnes?)
        // Factors in Excel row 16 were like 7.618 (X16).
        // AA16 = (Q16 * X16) / 1000. Q16 is Gal. X16 is kg CO2/Gal. Result AA16 is Tonnes CO2.
        
        // Dynamic Formula Evaluation
        $vars = [
            'activity_data' => $totalActivityData,
            'factor_co2' => $factor->factor_co2,
            'factor_ch4' => $factor->factor_ch4,
            'factor_n2o' => $factor->factor_n2o,
            'factor_nf3' => $factor->factor_nf3 ?? 0,
            'factor_sf6' => $factor->factor_sf6 ?? 0,
            'gwp_co2' => self::GWP_CO2,
            'gwp_ch4' => self::GWP_CH4,
            'gwp_n2o' => self::GWP_N2O,
            'gwp_nf3' => self::GWP_NF3,
            'gwp_sf6' => self::GWP_SF6,
        ];

        if ($factor->formula) {
            $totalCO2e = $this->formulaService->evaluate($factor->formula->expression, $vars);
        }
        
        // Standard Calculation (Combustion)
        $emissionsCO2 = ($totalActivityData * $factor->factor_co2) / 1000;
        $emissionsCH4 = ($totalActivityData * $factor->factor_ch4) / 1000;
        $emissionsN2O = ($totalActivityData * $factor->factor_n2o) / 1000;
        $emissionsNF3 = ($totalActivityData * ($factor->factor_nf3 ?? 0)) / 1000;
        $emissionsSF6 = ($totalActivityData * ($factor->factor_sf6 ?? 0)) / 1000;
        
        $co2e_CO2 = $emissionsCO2 * self::GWP_CO2;
        $co2e_CH4 = $emissionsCH4 * self::GWP_CH4;
        $co2e_N2O = $emissionsN2O * self::GWP_N2O;
        $co2e_NF3 = $emissionsNF3 * self::GWP_NF3;
        $co2e_SF6 = $emissionsSF6 * self::GWP_SF6;
        
        $totalCO2e = $co2e_CO2 + $co2e_CH4 + $co2e_N2O + $co2e_NF3 + $co2e_SF6;

        // Formula Override (if specific total formula exists)
        if ($factor->formula && $factor->formula->name === 'Total CO2e') {
             $totalCO2e = $this->formulaService->evaluate($factor->formula->expression, $vars);
        }

        // 4. Uncertainty Calculation (Root Sum Squares of Absolute Uncertainties)
        // Excel Logic: U_total = Sqrt( Sum( (Ui * Ei)^2 ) ) / Total_E
        
        // Activity Data Relative Uncertainty
        $u_activity = $activityDataUncertainty;
        
        // Factor Uncertainties (Relative, assuming stored as % in DB so divide by 100)
        // Currently we only have explicit 'uncertainty_upper' in DB, applying to all or CO2?
        // Excel has specific uncertainties per gas (Z, AG, AN).
        // For MVP, we use the stored uncertainty for CO2 (dominant).
        // And we might need default high uncertainties for CH4/N2O if not stored.
        // Excel Z16 (CO2) ~ 0.2%. AG16 (CH4) ~ 110%. AN16 (N2O) ~ 11%.
        // Our DB 'uncertainty_upper' is 0.234 (ratio?) or 0.234%? 
        // We decided to store 0.234 meaning 0.234%. So divide by 100.
        
        $u_fact_co2 = ($factor->uncertainty_upper ?? 0) / 100.0;
        // Hardcode defaults for minor gases if not in DB, roughly matching Excel or standard IPCC
        $u_fact_ch4 = 1.1; // 110%
        $u_fact_n2o = 0.11; // 11%
        $u_fact_nf3 = 0.11; // 11%
        $u_fact_sf6 = 0.11; // 11%

        // Absolute Uncertainties (in Tonnes CO2e)
        // U_abs_gas = Emissions_CO2e * Sqrt( U_act^2 + U_fact_gas^2 )
        
        $u_abs_co2 = $co2e_CO2 * sqrt(pow($u_activity, 2) + pow($u_fact_co2, 2));
        $u_abs_ch4 = $co2e_CH4 * sqrt(pow($u_activity, 2) + pow($u_fact_ch4, 2));
        $u_abs_n2o = $co2e_N2O * sqrt(pow($u_activity, 2) + pow($u_fact_n2o, 2));
        $u_abs_nf3 = $co2e_NF3 * sqrt(pow($u_activity, 2) + pow($u_fact_nf3, 2));
        $u_abs_sf6 = $co2e_SF6 * sqrt(pow($u_activity, 2) + pow($u_fact_sf6, 2));
        
        $total_abs_uncertainty_sq = pow($u_abs_co2, 2) + pow($u_abs_ch4, 2) + pow($u_abs_n2o, 2) + pow($u_abs_nf3, 2) + pow($u_abs_sf6, 2);
        $total_abs_uncertainty = sqrt($total_abs_uncertainty_sq);
        
        $combinedUncertainty = $totalCO2e > 0 ? ($total_abs_uncertainty / $totalCO2e) : 0.0;

        return [
            'emissions_co2' => $emissionsCO2,
            'emissions_ch4' => $emissionsCH4,
            'emissions_n2o' => $emissionsN2O,
            'emissions_nf3' => $emissionsNF3,
            'emissions_sf6' => $emissionsSF6,
            'calculated_co2e' => $totalCO2e,
            'uncertainty_result' => $combinedUncertainty * 100, // Convert to %
            'activity_data_total' => $totalActivityData,
            'activity_data_stdev' => $stdev,
        ];
    }

    private static function calculateStdev(array $data, float $mean): float
    {
        $n = count($data);
        if ($n <= 1) return 0.0;
        
        $carry = 0.0;
        foreach ($data as $val) {
            $d = ((float)$val) - $mean;
            $carry += $d * $d;
        }
        return sqrt($carry / ($n - 1));
    }
}
