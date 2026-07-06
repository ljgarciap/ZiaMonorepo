<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AssertsCompanyAccess;
use Illuminate\Http\Request;
use App\Models\AuditorAssignment;
use App\Models\Company;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use AssertsCompanyAccess;

    public function summary(Request $request)
    {
        $requestCompanyId = $request->query('company_id');
        $periodId = $request->query('period_id');

        if (!$requestCompanyId || !$periodId) {
            return response()->json(['error' => 'Company and Period are required'], 400);
        }

        // IDOR fix (fast-follow de H4, diseño finalizado por Cybersecurity): el
        // período es la fuente de verdad del scoping de empresa, nunca el
        // company_id que manda el cliente. X-Company-ID / company_id quedan como
        // "hint" de UI (qué empresa preseleccionar), no como fuente de autorización.
        $period = Period::find($periodId);
        if (!$period) {
            return response()->json(['error' => 'Period not found'], 404);
        }

        if ((int) $requestCompanyId !== (int) $period->company_id) {
            return response()->json(['error' => "company_id does not match the period's company"], 400);
        }

        $companyId = $period->company_id;
        $company = Company::find($companyId);

        $user = auth()->user();
        $activeRole = $request->header('X-Context-Role') ?: $user->role;

        if ($accessError = $this->assertCompanyPeriodAccess($user, $activeRole, $company, $period)) {
            return $accessError;
        }

        // Matriz CRUD spec 1.2.3: "Dashboard: Usuario = R (métricas propias)" — a
        // diferencia de Admin/Superadmin (empresa completa), el rol Usuario solo debe
        // ver el consolidado de lo que él mismo capturó, no el de toda la empresa.
        // company_wide=1 fuerza el total de toda la empresa aunque el rol sea 'user' —
        // lo usan los reportes oficiales (PDF/Excel de período), que son documentos de
        // cumplimiento a nivel empresa, no la vista personal del dashboard interactivo.
        $ownScopeOnly = $activeRole === 'user' && !$request->boolean('company_wide');

        // Fetch emissions for the given period — scoped a las propias si aplica.
        // We join with factors and categories to get names and scopes
        $emissionsQuery = \App\Models\CarbonEmission::where('period_id', $periodId)
            ->with(['factor.category']);

        if ($ownScopeOnly) {
            $emissionsQuery->where('user_id', auth()->id());
        }

        $emissions = $emissionsQuery->get();

        $huellaTotal = $emissions->sum('calculated_co2e');

        // Group by Scope
        $scopes = [
            1 => ['total' => 0, 'label' => 'Alcance 1', 'color' => '#1a237e'],
            2 => ['total' => 0, 'label' => 'Alcance 2', 'color' => '#00897b'],
            3 => ['total' => 0, 'label' => 'Alcance 3', 'color' => '#f59e0b'],
        ];

        $details = [];

        foreach ($emissions as $emission) {
            // Use scope_id directly as it corresponds to the key (1, 2, 3)
            // Using ->scope would return the Model object, causing TypeError
            $scope = $emission->factor->category->scope_id ?? 3;
            if (isset($scopes[$scope])) {
                $scopes[$scope]['total'] += $emission->calculated_co2e;
            }

            $details[] = [
                'scope' => $scope,
                'source' => $emission->factor->name,
                'total' => round($emission->calculated_co2e, 4),
                'percentage' => $huellaTotal > 0 ? round(($emission->calculated_co2e / $huellaTotal) * 100, 2) : 0
            ];
        }

        // Prepare response structure
        $alcancesRes = [];
        $donutData = [];
        foreach ($scopes as $sNum => $sInfo) {
            $alcancesRes['scope_' . $sNum] = [
                'total' => round($sInfo['total'], 2),
                'percentage' => $huellaTotal > 0 ? round(($sInfo['total'] / $huellaTotal) * 100) : 0,
                'neutralizado' => 0 // Future field
            ];
            $donutData[] = [
                'label' => $sInfo['label'],
                'value' => round($sInfo['total'], 2),
                'color' => $sInfo['color']
            ];
        }

        // Equivalency Logic: ~0.5 tCO2e is what one person consumes annually in energy (typical factor)
        $eqFactor = 0.5;
        $eqValue = $huellaTotal > 0 ? round($huellaTotal / $eqFactor, 1) : 0;

        $floorSqm     = $company ? ($company->floor_sqm ?? 0) : 0;
        $numEmployees = $company ? ($company->num_employees ?? 0) : 0;

        // Sin sentido para scope propio: dividir la huella de un individuo entre
        // metros/empleados de toda la empresa no es una métrica coherente.
        $intensidadKpis = $ownScopeOnly ? null : [
            'tco2e_por_m2'        => ($floorSqm > 0)     ? round($huellaTotal / $floorSqm, 4)     : null,
            'tco2e_por_empleado'  => ($numEmployees > 0)  ? round($huellaTotal / $numEmployees, 4) : null,
        ];

        // A01: Panel de completitud para admin y superadmin
        // H4 (QA 2026-07-05, regla de Cybersecurity): default-deny — la clave
        // admin_panel debe estar AUSENTE del array de respuesta para roles no
        // privilegiados, no presente con valor null (evita filtrar la forma de
        // datos admin-only vía inspección de red a roles sin permiso).
        $adminPanel = null;
        $isPrivilegedRole = in_array($activeRole, ['admin', 'superadmin']);
        if ($isPrivilegedRole) {
            // Emisiones por unidad operativa (una query con join)
            $byUnit = DB::table('carbon_emissions')
                ->leftJoin('operational_units', 'carbon_emissions.unit_id', '=', 'operational_units.id')
                ->where('carbon_emissions.period_id', $periodId)
                ->whereNull('carbon_emissions.deleted_at')
                ->select(
                    'operational_units.id as unit_id',
                    'operational_units.name as unit_name',
                    DB::raw('SUM(carbon_emissions.calculated_co2e) as total_co2e'),
                    DB::raw('COUNT(*) as entries')
                )
                ->groupBy('operational_units.id', 'operational_units.name')
                ->orderByDesc('total_co2e')
                ->get();

            // Emisiones por usuario
            $byUser = DB::table('carbon_emissions')
                ->join('users', 'carbon_emissions.user_id', '=', 'users.id')
                ->where('carbon_emissions.period_id', $periodId)
                ->whereNull('carbon_emissions.deleted_at')
                ->select(
                    'users.id as user_id',
                    'users.name as user_name',
                    DB::raw('SUM(carbon_emissions.calculated_co2e) as total_co2e'),
                    DB::raw('COUNT(*) as entries')
                )
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total_co2e')
                ->get();

            // Total usuarios con rol 'user' asignados a esta empresa
            $totalUsers = DB::table('company_user')
                ->join('users', 'users.id', '=', 'company_user.user_id')
                ->where('company_user.company_id', $companyId)
                ->where('users.role', 'user')
                ->whereNull('users.deleted_at')
                ->count();

            $usersWithData = $byUser->count();

            // Añadir porcentaje sobre huella total a cada fila
            $byUnitMapped = $byUnit->map(fn($r) => [
                'unit_id'    => $r->unit_id,
                'unit_name'  => $r->unit_name ?? 'Sin unidad',
                'total_co2e' => round((float) $r->total_co2e, 2),
                'percentage' => $huellaTotal > 0 ? round(((float)$r->total_co2e / $huellaTotal) * 100, 2) : 0,
                'entries'    => (int) $r->entries,
            ])->values();

            $byUserMapped = $byUser->map(fn($r) => [
                'user_id'    => $r->user_id,
                'user_name'  => $r->user_name,
                'total_co2e' => round((float) $r->total_co2e, 2),
                'percentage' => $huellaTotal > 0 ? round(((float)$r->total_co2e / $huellaTotal) * 100, 2) : 0,
                'entries'    => (int) $r->entries,
            ])->values();

            $adminPanel = [
                'registration_progress' => [
                    'users_with_data' => $usersWithData,
                    'total_users'     => $totalUsers,
                    'percentage'      => $totalUsers > 0 ? round(($usersWithData / $totalUsers) * 100) : 0,
                ],
                'by_unit' => $byUnitMapped,
                'by_user' => $byUserMapped,
            ];
        }

        return response()->json([
            'huella_total' => round($huellaTotal, 2),
            'neutralizados' => 0,
            'scope' => $ownScopeOnly ? 'own' : 'company',
            ...($isPrivilegedRole ? ['admin_panel' => $adminPanel] : []),
            'alcances' => $alcancesRes,
            'equivalency' => [
                'value' => $eqValue,
                'label' => 'Personas consumiendo energía eléctrica anualmente'
            ],
            'intensidad_kpis' => $intensidadKpis,
            'chart_data' => [
                'donut' => $donutData,
                'details' => collect($details)->sortByDesc('total')->values()->all()
            ]
        ]);
    }

    public function trends(Request $request)
    {
        $requestCompanyId = $request->query('company_id');

        if (!$requestCompanyId) {
            return response()->json(['error' => 'Company is required'], 400);
        }

        $company = Company::find($requestCompanyId);
        if (!$company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $companyId = $company->id;

        $user = auth()->user();
        $activeRole = $request->header('X-Context-Role') ?: $user->role;

        // IDOR fix: trends() no recibe period_id (agrega todos los períodos de la
        // empresa), a diferencia de summary(). No hay un período único que pueda
        // servir de fuente de verdad aquí — el company_id es en sí el ancho de
        // scoping, así que se valida directamente contra $company. Ver el caso
        // 'auditor' dentro de assertCompanyPeriodAccess() para la limitación
        // conocida cuando no se pasa $period.
        if ($accessError = $this->assertCompanyPeriodAccess($user, $activeRole, $company, null)) {
            return $accessError;
        }

        $ownScopeOnly = $activeRole === 'user';

        // Get all periods for this company, ordered by year
        $periodsQuery = Period::where('company_id', $companyId)->orderBy('year');

        // Decisión de producto (2026-07-05): un auditor solo debe ver tendencia
        // de los períodos para los que tiene asignación activa, no el histórico
        // completo de la empresa — su acceso es por período, no por empresa.
        if ($activeRole === 'auditor') {
            $assignedPeriodIds = AuditorAssignment::where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->active()
                ->pluck('period_id');

            $periodsQuery->whereIn('id', $assignedPeriodIds);
        }

        $periods = $periodsQuery->get();

        // Temporal Evolution: Emissions by period
        $periodLabels = [];
        $periodData = [];

        foreach ($periods as $period) {
            $periodLabels[] = (string)$period->year;
            $periodQuery = \App\Models\CarbonEmission::where('period_id', $period->id);
            if ($ownScopeOnly) {
                $periodQuery->where('user_id', auth()->id());
            }
            $periodData[] = round($periodQuery->sum('calculated_co2e'), 2);
        }

        // Category Distribution: Emissions by category for the latest period
        $latestPeriod = $periods->last();
        $categoryLabels = [];
        $categoryData = [];

        if ($latestPeriod) {
            $categoryQuery = \App\Models\CarbonEmission::where('period_id', $latestPeriod->id)
                ->with(['factor.category']);
            if ($ownScopeOnly) {
                $categoryQuery->where('user_id', auth()->id());
            }
            $emissionsByCategory = $categoryQuery->get()
                ->groupBy(function($emission) {
                    return $emission->factor->category->name ?? 'Sin categoría';
                });

            foreach ($emissionsByCategory as $categoryName => $emissions) {
                $categoryLabels[] = $categoryName;
                $categoryData[] = round($emissions->sum('calculated_co2e'), 2);
            }
        }

        return response()->json([
            'revenue_trend' => [
                'labels' => $periodLabels,
                'datasets' => [
                    [
                        'label' => 'Emisiones (tCO2e)',
                        'data' => $periodData,
                        'borderColor' => '#1a237e',
                        'backgroundColor' => 'rgba(26, 35, 126, 0.1)',
                        'tension' => 0.4
                    ]
                ]
            ],
            'sales_quantity' => [
                'labels' => $categoryLabels,
                'datasets' => [
                    [
                        'label' => 'Emisiones (tCO2e)',
                        'data' => $categoryData,
                        'backgroundColor' => ['#1a237e', '#00897b', '#f59e0b', '#e91e63', '#9c27b0', '#ff9800']
                    ]
                ]
            ]
        ]);
    }

}
