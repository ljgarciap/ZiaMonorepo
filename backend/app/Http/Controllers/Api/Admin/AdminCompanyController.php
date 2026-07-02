<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Period;
use App\Models\IotDevice;
use App\Models\TelemetryAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminCompanyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/companies",
     *     summary="List all companies with periods (Admin)",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of companies")
     * )
     */
    public function index()
    {
        $user = auth()->user();
        $activeRole = request()->header('X-Context-Role') ?: $user->role;
        if ($activeRole === 'superadmin') {
            return response()->json(Company::withTrashed()->with(['periods', 'sector'])->get());
        }

        return response()->json($user->companies()->withTrashed()->with(['periods', 'sector'])->get());
    }

    /**
     * @OA\Post(
     *     path="/api/admin/companies",
     *     summary="Create a new company",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "nit"},
     *             @OA\Property(property="name", type="string", example="Acme Corp"),
     *             @OA\Property(property="nit", type="string", example="123456789-0"),
             *             @OA\Property(property="company_sector_id", type="integer", example=1),
     *             @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'nit'                   => 'nullable|string|max:20',
            'company_sector_id'     => 'nullable|exists:company_sectors,id',
            'logo_url'              => 'nullable|url',
            'num_employees'         => 'nullable|integer|min:1',
            'floor_sqm'             => 'nullable|numeric|min:0',
            'contact_email'         => 'nullable|email|max:255',
            'contact_phone'         => 'nullable|string|max:30',
            'legal_rep'             => 'nullable|string|max:255',
            'address'               => 'nullable|string|max:500',
            'base_year'             => 'nullable|integer|min:1990|max:2100',
            'methodology'           => 'nullable|string|in:GHG_PROTOCOL,ISO_14064,IPCC,OTHER',
            'decarbonization_goal'  => 'nullable|string|max:1000',
            'decarbonization_year'  => 'nullable|integer|min:2020|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $company = Company::create($request->only([
            'name', 'nit', 'company_sector_id', 'logo_url', 'num_employees', 'floor_sqm',
            'contact_email', 'contact_phone', 'legal_rep', 'address',
            'base_year', 'methodology', 'decarbonization_goal', 'decarbonization_year',
        ]));
        return response()->json($company->load('sector'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/companies/{company}",
     *     summary="Update a company",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(Request $request, Company $company)
    {
        $data = $request->only([
            'name', 'nit', 'company_sector_id', 'logo_url', 'num_employees', 'floor_sqm',
            'contact_email', 'contact_phone', 'legal_rep', 'address',
            'base_year', 'methodology', 'decarbonization_goal', 'decarbonization_year',
        ]);

        // Cambiar la estructura metodológica invalida una aprobación previa del Superadmin.
        $methodologyFields = ['base_year', 'methodology', 'decarbonization_goal', 'decarbonization_year'];
        if ($company->is_methodology_approved && array_intersect_key($data, array_flip($methodologyFields))) {
            $data['is_methodology_approved'] = false;
            $data['methodology_approved_at'] = null;
            $data['methodology_approved_by'] = null;
        }

        $company->update($data);
        return response()->json($company->load('sector'));
    }

    /**
     * @OA\Post(
     *     path="/api/admin/companies/{company}/approve-methodology",
     *     summary="Superadmin approves the company's methodological structure (ISO 14064-1 / GHG Protocol)",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Approved")
     * )
     */
    public function approveMethodology(Company $company)
    {
        $company->update([
            'is_methodology_approved' => true,
            'methodology_approved_at' => now(),
            'methodology_approved_by' => auth()->id(),
        ]);

        return response()->json($company->load('sector'));
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/companies/{company}",
     *     summary="Soft delete a company",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted")
     * )
     */
    public function destroy(Company $company)
    {
        $company->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/companies/{company}/periods",
     *     summary="Add a new period to a company",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"year", "status"},
     *             @OA\Property(property="year", type="integer", example=2024),
     *             @OA\Property(property="status", type="string", example="open")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Period added")
     * )
     */
    public function addPeriod(Request $request, Company $company)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer',
            'status' => 'required|string|in:open,closed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $period = $company->periods()->create($request->all());
        return response()->json($period, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/periods/{period}",
     *     summary="Update a period",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function updatePeriod(Request $request, Period $period)
    {
        $period->update($request->all());
        return response()->json($period);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/periods/{period}",
     *     summary="Delete a period",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted")
     * )
     */
    public function deletePeriod(Period $period)
    {
        $period->delete();
        return response()->json(null, 204);
    }

    public function closePeriod(Period $period)
    {
        $period->update(['status' => 'closed']);
        return response()->json($period);
    }

    public function reopenPeriod(Period $period)
    {
        $period->update(['status' => 'open']);
        return response()->json($period);
    }

    // SA-15: ciclo de vida de períodos — transiciones adicionales
    public function sendToReview(Period $period)
    {
        if (!in_array($period->status, ['open', 'active'])) {
            return response()->json(['message' => 'Solo períodos abiertos pueden pasar a revisión.'], 422);
        }
        $period->update(['status' => 'in_review']);
        return response()->json($period);
    }

    public function archivePeriod(Period $period)
    {
        if ($period->status !== 'closed') {
            return response()->json(['message' => 'Solo períodos cerrados pueden archivarse.'], 422);
        }
        $period->update(['status' => 'archived']);
        return response()->json($period);
    }

    // SA-17: KPIs globales de plataforma para el dashboard del superadmin
    public function platformStats()
    {
        $totalCompanies  = Company::count();
        $activeCompanies = Company::whereNull('deleted_at')->count();
        $openPeriods     = Period::where('status', 'open')->orWhereNull('status')->count();
        $closedPeriods   = Period::where('status', 'closed')->count();

        $totalEmissions = round(
            DB::table('carbon_emissions')->whereNull('deleted_at')->sum('calculated_co2e'), 2
        );

        $totalUsers  = DB::table('users')->whereNotIn('role', ['superadmin'])->whereNull('deleted_at')->count();
        $activeUsers = DB::table('users')
            ->whereNotIn('role', ['superadmin'])
            ->whereNull('deleted_at')
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        $totalDevices     = IotDevice::whereNull('deleted_at')->count();
        $pendingAlerts    = TelemetryAlert::where('resolved', false)->count();

        // Top 5 empresas por emisiones este año
        $topCompanies = DB::table('carbon_emissions')
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->join('companies', 'periods.company_id', '=', 'companies.id')
            ->whereNull('carbon_emissions.deleted_at')
            ->where('periods.year', now()->year)
            ->groupBy('companies.id', 'companies.name')
            ->select('companies.name', DB::raw('SUM(carbon_emissions.calculated_co2e) as total_co2e'))
            ->orderByDesc('total_co2e')
            ->limit(5)
            ->get()
            ->map(fn($r) => ['name' => $r->name, 'total_co2e' => round((float)$r->total_co2e, 2)]);

        // Evolución de emisiones por año (últimos 5 años)
        $emissionsByYear = DB::table('carbon_emissions')
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->whereNull('carbon_emissions.deleted_at')
            ->where('periods.year', '>=', now()->year - 4)
            ->groupBy('periods.year')
            ->select('periods.year', DB::raw('SUM(carbon_emissions.calculated_co2e) as total_co2e'))
            ->orderBy('periods.year')
            ->get()
            ->map(fn($r) => ['year' => $r->year, 'total_co2e' => round((float)$r->total_co2e, 2)]);

        return response()->json([
            'companies'       => ['total' => $totalCompanies, 'active' => $activeCompanies],
            'periods'         => ['open' => $openPeriods, 'closed' => $closedPeriods],
            'emissions'       => ['total_co2e' => $totalEmissions],
            'users'           => ['total' => $totalUsers, 'active_30d' => $activeUsers],
            'iot'             => ['devices' => $totalDevices, 'pending_alerts' => $pendingAlerts],
            'top_companies'   => $topCompanies,
            'emissions_trend' => $emissionsByYear,
        ]);
    }

    // SA-11: reporte PDF global multiorganización
    public function platformReport()
    {
        // Reutilizar datos del platformStats
        $stats = json_decode(json_encode($this->platformStats()->getData()), true);

        // Desglose por alcance — todas las empresas
        $scopeBreakdown = DB::table('carbon_emissions')
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->join('scopes', 'carbon_emissions.scope_id', '=', 'scopes.id')
            ->whereNull('carbon_emissions.deleted_at')
            ->groupBy('scopes.id', 'scopes.name', 'scopes.number')
            ->select(
                'scopes.number as scope_number',
                'scopes.name as scope_name',
                DB::raw('SUM(carbon_emissions.calculated_co2e) as total_co2e')
            )
            ->orderBy('scopes.number')
            ->get()
            ->map(fn($r) => [
                'scope_number' => $r->scope_number,
                'scope_name'   => $r->scope_name,
                'total_co2e'   => round((float)$r->total_co2e, 2),
            ]);

        $pdf = Pdf::loadView('reports.platform', [
            'stats'          => $stats,
            'scopeBreakdown' => $scopeBreakdown,
        ])->setPaper('a4', 'portrait');

        $filename = 'zia-informe-plataforma-' . now()->format('Ymd') . '.pdf';
        return $pdf->download($filename);
    }
}
