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
            'name'              => 'required|string|max:255',
            'nit'               => 'nullable|string|max:20',
            'company_sector_id' => 'required|exists:company_sectors,id',
            'logo_url'          => 'nullable|url',
            'num_employees'     => 'nullable|integer|min:1',
            'floor_sqm'         => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $company = Company::create($request->only([
            'name', 'nit', 'company_sector_id', 'logo_url', 'num_employees', 'floor_sqm',
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
        $company->update($request->only([
            'name', 'nit', 'company_sector_id', 'logo_url', 'num_employees', 'floor_sqm',
        ]));
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
        $period->update(['status' => 'active']);
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
}
