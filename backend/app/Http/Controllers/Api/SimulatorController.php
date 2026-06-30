<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimulatorScenario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SimulatorController extends Controller
{
    public function index(): JsonResponse
    {
        $scenarios = SimulatorScenario::where('is_active', true)
            ->orderBy('scope')
            ->orderBy('id')
            ->get();

        return response()->json($scenarios);
    }

    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'scenario_ids' => 'required|array|min:1',
            'scenario_ids.*' => 'integer|exists:simulator_scenarios,id',
            'years' => 'integer|min:1|max:30',
        ]);

        $years = $request->input('years', 1);

        $scenarios = SimulatorScenario::whereIn('id', $request->scenario_ids)
            ->where('is_active', true)
            ->get();

        $breakdown = $scenarios->map(fn($s) => [
            'id'                  => $s->id,
            'code'                => $s->code,
            'name'                => $s->name,
            'scope'               => $s->scope,
            'category'            => $s->category,
            'annual_co2e_tco2e'   => $s->annual_co2e_tco2e,
            'annual_savings_cop'  => $s->annual_savings_cop,
            'total_co2e_tco2e'    => round($s->annual_co2e_tco2e * $years, 4),
            'total_savings_cop'   => $s->annual_savings_cop * $years,
        ]);

        $totalCo2eAnnual   = $breakdown->sum('annual_co2e_tco2e');
        $totalSavingsAnnual = $breakdown->sum('annual_savings_cop');

        // Year-by-year projection
        $projection = collect(range(1, $years))->map(fn($y) => [
            'year'         => $y,
            'co2e_tco2e'   => round($totalCo2eAnnual * $y, 4),
            'savings_cop'  => $totalSavingsAnnual * $y,
        ]);

        return response()->json([
            'breakdown'  => $breakdown,
            'years'      => $years,
            'totals' => [
                'annual_co2e_tco2e'    => round($totalCo2eAnnual, 4),
                'annual_savings_cop'   => $totalSavingsAnnual,
                'total_co2e_tco2e'     => round($totalCo2eAnnual * $years, 4),
                'total_savings_cop'    => $totalSavingsAnnual * $years,
            ],
            'projection' => $projection,
        ]);
    }
}
