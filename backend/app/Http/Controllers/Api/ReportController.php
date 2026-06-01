<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Exports\EmissionExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function pdfSummary(Period $period)
    {
        $period->load(['company', 'emissions.factor.category.scope', 'emissions.factor.unit']);

        // Use the same logic as DashboardController to get the summary
        $dashboardController = new DashboardController();
        $request = new Request([
            'company_id' => $period->company_id,
            'period_id' => $period->id
        ]);
        
        $summaryResponse = $dashboardController->summary($request);
        $summary = json_decode($summaryResponse->getContent(), true);

        $pdf = Pdf::loadView('reports.summary', compact('period', 'summary'));
        
        $filename = 'zia_reporte_' . str_replace(' ', '_', strtolower($period->company->name)) . '_' . $period->year . '_' . date('Y-m-d') . '.pdf';
        
        return $pdf->download($filename);
    }

    public function excelExport(Period $period)
    {
        $filename = 'zia_datos_' . str_replace(' ', '_', strtolower($period->company->name)) . '_' . $period->year . '_' . date('Y-m-d') . '.xlsx';
        return Excel::download(new EmissionExport($period->id), $filename);
    }
}
