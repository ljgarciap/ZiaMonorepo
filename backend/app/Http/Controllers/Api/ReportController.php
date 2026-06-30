<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbonEmission;
use App\Models\Period;
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
}
