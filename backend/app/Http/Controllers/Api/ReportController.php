<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbonEmission;
use App\Models\Period;
use App\Models\TelemetryReading;
use App\Models\TelemetryAlert;
use App\Exports\EmissionExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function pdfSummary(Period $period)
    {
        $period->load(['company', 'emissions.factor.category.scope', 'emissions.factor.unit']);

        // Dashboard summary (scope totals + equivalency)
        $dashboardController = new DashboardController();
        $request = new Request([
            'company_id' => $period->company_id,
            'period_id'  => $period->id,
        ]);
        $summaryResponse = $dashboardController->summary($request);
        $summary = json_decode($summaryResponse->getContent(), true);

        // ── Intensity indicators (GRI 305-4) ─────────────────────────────────
        $totalCo2e            = $summary['huella_total'] ?? 0;
        $floorSqm             = (int) ($period->company->floor_sqm ?? 0);
        $numEmployees         = (int) ($period->company->num_employees ?? 0);
        $intensityPerSqm      = $floorSqm  > 0 ? round($totalCo2e / $floorSqm, 6)  : null;
        $intensityPerEmployee = $numEmployees > 0 ? round($totalCo2e / $numEmployees, 4) : null;

        // ── Net balance / GRI 305-5 ──────────────────────────────────────────
        $grossEmissions   = (float) $period->emissions->where('calculated_co2e', '>', 0)->sum('calculated_co2e');
        $removals         = abs((float) $period->emissions->where('calculated_co2e', '<', 0)->sum('calculated_co2e'));
        $carbonStored     = (float) $period->emissions->sum('carbon_stored');
        $avoidedEmissions = (float) $period->emissions->sum('avoided_emissions');
        $biogenicTotal    = (float) $period->emissions->sum('biogenic_co2e');
        $netBalance       = $grossEmissions - $removals;

        // ── Multi-year comparison (up to 5 years including current) ──────────
        $years = range(max(2019, $period->year - 4), $period->year);

        $comparisonData = CarbonEmission::query()
            ->select(
                'periods.year',
                DB::raw('SUM(CASE WHEN scopes.number = 1 THEN carbon_emissions.calculated_co2e ELSE 0 END) as scope1'),
                DB::raw('SUM(CASE WHEN scopes.number = 2 THEN carbon_emissions.calculated_co2e ELSE 0 END) as scope2'),
                DB::raw('SUM(CASE WHEN scopes.number = 3 THEN carbon_emissions.calculated_co2e ELSE 0 END) as scope3'),
                DB::raw('SUM(carbon_emissions.calculated_co2e) as total')
            )
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->join('emission_factors', 'carbon_emissions.emission_factor_id', '=', 'emission_factors.id')
            ->join('emission_categories', 'emission_factors.emission_category_id', '=', 'emission_categories.id')
            ->join('scopes', 'emission_categories.scope_id', '=', 'scopes.id')
            ->where('periods.company_id', $period->company_id)
            ->whereIn('periods.year', $years)
            ->groupBy('periods.year')
            ->orderBy('periods.year')
            ->get();

        $pdf = Pdf::loadView('reports.summary', compact(
            'period',
            'summary',
            'intensityPerSqm',
            'intensityPerEmployee',
            'floorSqm',
            'numEmployees',
            'grossEmissions',
            'removals',
            'carbonStored',
            'avoidedEmissions',
            'biogenicTotal',
            'netBalance',
            'comparisonData'
        ));

        $filename = 'zia_reporte_'
            . str_replace(' ', '_', strtolower($period->company->name))
            . '_' . $period->year
            . '_' . date('Y-m-d')
            . '.pdf';

        return $pdf->download($filename);
    }

    public function excelExport(Period $period)
    {
        $filename = 'zia_datos_'
            . str_replace(' ', '_', strtolower($period->company->name))
            . '_' . $period->year
            . '_' . date('Y-m-d')
            . '.xlsx';

        return Excel::download(new EmissionExport($period->id), $filename);
    }

    // A10: Reporte de Avance — evolución respecto al año base
    public function progressReport(Period $period)
    {
        $period->load('company');

        // Todos los períodos de la empresa, en orden cronológico
        $allPeriods = Period::where('company_id', $period->company_id)->orderBy('year')->get();
        $basePeriod = $allPeriods->first();

        // Emisiones por alcance para cada período (query única)
        $emissionsByPeriod = CarbonEmission::query()
            ->select(
                'periods.year',
                DB::raw('SUM(CASE WHEN scopes.number = 1 THEN carbon_emissions.calculated_co2e ELSE 0 END) as scope1'),
                DB::raw('SUM(CASE WHEN scopes.number = 2 THEN carbon_emissions.calculated_co2e ELSE 0 END) as scope2'),
                DB::raw('SUM(CASE WHEN scopes.number = 3 THEN carbon_emissions.calculated_co2e ELSE 0 END) as scope3'),
                DB::raw('SUM(carbon_emissions.calculated_co2e) as total')
            )
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->join('emission_factors', 'carbon_emissions.emission_factor_id', '=', 'emission_factors.id')
            ->join('emission_categories', 'emission_factors.emission_category_id', '=', 'emission_categories.id')
            ->join('scopes', 'emission_categories.scope_id', '=', 'scopes.id')
            ->where('periods.company_id', $period->company_id)
            ->whereNull('carbon_emissions.deleted_at')
            ->groupBy('periods.year')
            ->orderBy('periods.year')
            ->get();

        $baseData    = $emissionsByPeriod->firstWhere('year', $basePeriod?->year);
        $currentData = $emissionsByPeriod->firstWhere('year', $period->year);

        // Variaciones absolutas y porcentuales respecto al año base
        $progress = null;
        if ($baseData && $currentData && (float)$baseData->total > 0) {
            $baseTotal = (float) $baseData->total;
            $curTotal  = (float) $currentData->total;
            $progress  = [
                'absolute_change' => round($curTotal - $baseTotal, 2),
                'pct_change'      => round((($curTotal - $baseTotal) / $baseTotal) * 100, 2),
                'scope1_change'   => round((float)$currentData->scope1 - (float)$baseData->scope1, 2),
                'scope2_change'   => round((float)$currentData->scope2 - (float)$baseData->scope2, 2),
                'scope3_change'   => round((float)$currentData->scope3 - (float)$baseData->scope3, 2),
            ];
        }

        $pdf = Pdf::loadView('reports.progress', compact(
            'period', 'basePeriod', 'emissionsByPeriod', 'baseData', 'currentData', 'progress'
        ));

        return $pdf->download(
            'zia_avance_'
            . str_replace(' ', '_', strtolower($period->company->name))
            . '_base' . ($basePeriod?->year ?? 'nd')
            . '_vs' . $period->year
            . '_' . date('Y-m-d') . '.pdf'
        );
    }

    // A10: Reporte IoT — telemetría del período
    public function iotReport(Period $period)
    {
        $period->load('company');

        $from = $period->year . '-01-01 00:00:00';
        $to   = $period->year . '-12-31 23:59:59';

        // Lecturas IoT para los dispositivos de esta empresa en el período
        $readings = TelemetryReading::with('device')
            ->join('iot_devices', 'telemetry_readings.device_id', '=', 'iot_devices.id')
            ->where('iot_devices.company_id', $period->company_id)
            ->whereNull('iot_devices.deleted_at')
            ->whereBetween('telemetry_readings.timestamp', [$from, $to])
            ->select('telemetry_readings.*')
            ->orderBy('telemetry_readings.timestamp')
            ->get();

        // Estadísticas por métrica
        $metricStats = $readings->groupBy('metric_name')->map(fn($g, $metric) => [
            'metric' => $metric,
            'count'  => $g->count(),
            'min'    => round($g->min('value'), 4),
            'max'    => round($g->max('value'), 4),
            'avg'    => round($g->avg('value'), 4),
            'sum'    => round($g->sum('value'), 2),
        ])->values();

        // Resumen por dispositivo
        $byDevice = $readings->groupBy(fn($r) => $r->device?->name ?? 'Desconocido')
            ->map(fn($g, $name) => [
                'name'    => $name,
                'count'   => $g->count(),
                'metrics' => $g->pluck('metric_name')->unique()->implode(', '),
            ])->values();

        // Alertas del período
        $alerts = TelemetryAlert::with('device')
            ->whereHas('device', fn($q) => $q->where('company_id', $period->company_id))
            ->whereBetween('detected_at', [$from, $to])
            ->orderByDesc('detected_at')
            ->limit(50)
            ->get();

        // Emisiones declaradas del período (para contraste)
        $declaredCo2e = round(CarbonEmission::where('period_id', $period->id)->sum('calculated_co2e'), 2);

        $pdf = Pdf::loadView('reports.iot', compact(
            'period', 'readings', 'metricStats', 'byDevice', 'alerts', 'declaredCo2e', 'from', 'to'
        ));

        return $pdf->download(
            'zia_iot_'
            . str_replace(' ', '_', strtolower($period->company->name))
            . '_' . $period->year
            . '_' . date('Y-m-d') . '.pdf'
        );
    }
}
