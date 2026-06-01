<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AIManager;
use App\Models\Company;
use App\Models\CarbonEmission;
use App\Models\TelemetryReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AISidecarController extends Controller
{
    protected $aiManager;

    public function __construct(AIManager $aiManager)
    {
        $this->aiManager = $aiManager;
    }

    /**
     * Get tailored ecological recommendations for the company context.
     */
    public function getRecommendations(Request $request)
    {
        // 1. Resolve selected company from request headers/context
        // The middleware sets active company in custom attributes or we query selected context
        $companyId = $request->header('X-Company-Context') ?? $request->input('company_id');
        
        if (!$companyId) {
            return response()->json(['error' => 'Company context header X-Company-Context is required'], 400);
        }

        $company = Company::findOrFail($companyId);

        // 2. Fetch recent emissions summaries
        $emissionsSummary = CarbonEmission::query()
            ->select('carbon_emissions.id', 'carbon_emissions.quantity', 'carbon_emissions.calculated_co2e', 'periods.year')
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->where('periods.company_id', $company->id)
            ->latest('carbon_emissions.created_at')
            ->limit(5)
            ->get()
            ->toArray();

        // 3. Fetch recent telemetry readings
        $telemetrySummary = TelemetryReading::query()
            ->select('telemetry_readings.metric_name', 'telemetry_readings.value', 'telemetry_readings.timestamp', 'iot_devices.name as device_name')
            ->join('iot_devices', 'telemetry_readings.device_id', '=', 'iot_devices.id')
            ->latest('telemetry_readings.timestamp')
            ->limit(10)
            ->get()
            ->toArray();

        // 4. Generate dynamic LLM suggestions
        $recommendations = $this->aiManager->generateRecommendations(
            $company->name,
            $emissionsSummary,
            $telemetrySummary
        );

        return response()->json([
            'company_name' => $company->name,
            'recommendations' => $recommendations,
            'timestamp' => now()->toIso8601String()
        ]);
    }
}
