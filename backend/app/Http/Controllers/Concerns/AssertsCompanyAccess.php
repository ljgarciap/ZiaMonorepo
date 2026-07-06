<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditorAssignment;
use App\Models\Company;
use App\Models\Period;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Verificación explícita de pertenencia a empresa/período — nunca confiar en
 * ContextAwareMiddleware para esto: ese middleware solo actúa cuando el header
 * X-Company-ID está presente, no cuando {company}/{period} vienen de la URL
 * (hallazgo real: varios controllers dejaban esto sin verificar del todo).
 *
 * Extraído de DashboardController::assertCompanyPeriodAccess tras encontrar el
 * mismo patrón duplicado (con variantes ligeramente distintas) en varios
 * controllers — usar este trait en vez de copiar la lógica de nuevo.
 */
trait AssertsCompanyAccess
{
    protected function assertCompanyPeriodAccess(User $user, string $activeRole, Company $company, ?Period $period = null): ?JsonResponse
    {
        if ($activeRole === 'superadmin') {
            return null;
        }

        if (in_array($activeRole, ['admin', 'user', 'iot_tech', 'viewer'])) {
            $belongs = $user->companies()
                ->where('companies.id', $company->id)
                ->wherePivot('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('company_user.expires_at')
                        ->orWhere('company_user.expires_at', '>', now());
                })
                ->exists();

            return $belongs ? null : response()->json(['error' => 'Sin permiso.'], 403);
        }

        if ($activeRole === 'auditor') {
            $query = AuditorAssignment::where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->active();

            if ($period) {
                $query->where('period_id', $period->id);
            }

            return $query->exists()
                ? null
                : response()->json(['error' => 'No tienes autorización de auditoría para esta empresa/período.'], 403);
        }

        return response()->json(['error' => 'Sin permiso.'], 403);
    }
}
