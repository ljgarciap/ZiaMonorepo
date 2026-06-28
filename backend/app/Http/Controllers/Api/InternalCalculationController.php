<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmissionFactor;
use App\Services\CarbonFootprintService;
use Illuminate\Http\Request;

class InternalCalculationController extends Controller
{
    public function __construct(private CarbonFootprintService $carbonService) {}

    /**
     * POST /api/internal/calculate
     * Called exclusively by the zia-agent Python microservice via Docker internal network.
     * Protected by X-Internal-Secret header — never exposed to public internet.
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'emission_factor_id' => 'required|integer|exists:emission_factors,id',
            'monthly_values'     => 'required|array|min:1',
            'monthly_values.*'   => 'numeric|min:0',
        ]);

        $factor  = EmissionFactor::with('formula')->findOrFail($validated['emission_factor_id']);
        $inputs  = array_map('floatval', $validated['monthly_values']);
        $results = $this->carbonService->calculate($inputs, $factor);

        return response()->json([
            'emission_factor_id' => $factor->id,
            'factor_name'        => $factor->name,
            'calculated_co2e'    => $results['calculated_co2e'],
            'emissions_co2'      => $results['emissions_co2'],
            'emissions_ch4'      => $results['emissions_ch4'],
            'emissions_n2o'      => $results['emissions_n2o'],
            'uncertainty_result' => $results['uncertainty_result'],
            'activity_data_total'=> $results['activity_data_total'],
            'unit'               => $factor->unit?->symbol,
        ]);
    }
}
