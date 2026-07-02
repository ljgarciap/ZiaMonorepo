<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $activeRole = $request->header('X-Context-Role') ?: $user->role;

        $query = ActivityLog::with('user:id,name,email,role')->orderBy('created_at', 'desc');

        if ($activeRole === 'superadmin') {
            // Superadmin: bitácora global completa (sin restricción de tenant)
        } elseif ($activeRole === 'admin') {
            // Admin: bitácora acotada a usuarios de sus empresas (Matriz v2: Bitácora de empresa = R)
            $companyIds = $user->companies->pluck('id');
            $tenantUserIds = DB::table('company_user')
                ->whereIn('company_id', $companyIds)
                ->pluck('user_id')
                ->unique();
            $query->whereIn('user_id', $tenantUserIds);
        } else {
            return response()->json(['message' => 'Forbidden: insufficient role.'], 403);
        }

        $this->applyCommonFilters($query, $request);

        return response()->json($query->paginate(20));
    }

    /**
     * GET /companies/{company}/audit-logs
     * Bitácora acotada a una empresa — el acceso del Auditor externo cubre
     * "bitácora de auditoría: solo periodo/empresa autorizado" (matriz CRUD spec 1.2.3).
     */
    public function companyIndex(Request $request, Company $company)
    {
        $this->authorizeCompanyAccess($company);

        $tenantUserIds = DB::table('company_user')
            ->where('company_id', $company->id)
            ->pluck('user_id')
            ->unique();

        $query = ActivityLog::with('user:id,name,email,role')
            ->whereIn('user_id', $tenantUserIds)
            ->orderBy('created_at', 'desc');

        $this->applyCommonFilters($query, $request);

        return response()->json($query->paginate(20));
    }

    private function applyCommonFilters($query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('model')) {
            $query->where('model', 'like', '%' . $request->model . '%');
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // SA-13: filtro por evento crítico (server-side, antes de paginar)
        if ($request->filled('critical_event')) {
            $this->applyCriticalEventFilter($query, $request->critical_event);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
    }

    /**
     * Superadmin: acceso total. Admin: solo su propia empresa. Auditor: solo
     * empresas donde su acceso está activo y no ha vencido (mismo criterio que
     * AuditObservationController / RoleMiddleware).
     */
    private function authorizeCompanyAccess(Company $company): void
    {
        $user = Auth::user();

        if ($user->role === 'superadmin') {
            return;
        }

        if ($user->role === 'admin') {
            $belongs = $user->companies()
                ->where('companies.id', $company->id)
                ->wherePivot('is_active', true)
                ->exists();

            abort_unless($belongs, 403);
            return;
        }

        if ($user->role === 'auditor') {
            $belongs = $user->companies()
                ->where('companies.id', $company->id)
                ->wherePivot('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('company_user.expires_at')
                        ->orWhere('company_user.expires_at', '>', now());
                })
                ->exists();

            abort_unless($belongs, 403, 'Tu acceso de auditoría a esta empresa no está vigente.');
            return;
        }

        abort(403);
    }

    /**
     * Same categories the frontend used to filter client-side (SA-13). Moved server-side
     * so pagination totals stay correct when a critical_event filter is active.
     */
    private function applyCriticalEventFilter($query, string $criticalEvent): void
    {
        $modelMap = [
            'factor_change' => [\App\Models\EmissionFactor::class],
            'role_change' => [\App\Models\User::class],
            'company_change' => [\App\Models\Company::class],
            'period_change' => [\App\Models\Period::class],
        ];

        if ($criticalEvent === 'deletion') {
            $query->where('action', 'deleted');
            return;
        }

        $models = $modelMap[$criticalEvent] ?? [];
        $query->whereIn('model', $models);
    }
}
