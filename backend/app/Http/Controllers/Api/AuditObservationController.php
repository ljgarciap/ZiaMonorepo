<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditObservation;
use App\Models\AuditorAssignment;
use App\Models\Company;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditObservationController extends Controller
{
    /**
     * GET /companies/{company}/periods/{period}/observations
     * Bitácora de hallazgos del Auditor externo para el período — visible a
     * Superadmin, al Admin de la empresa y al propio Auditor autorizado.
     */
    public function index(Company $company, Period $period)
    {
        $this->abortIfPeriodNotInCompany($period, $company);
        $this->authorizeAccess($company, $period);

        return response()->json(
            $period->auditObservations()
                ->with('user:id,name,role')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    /**
     * POST /companies/{company}/periods/{period}/observations
     * El Auditor externo deja un hallazgo u observación (solo texto — no
     * modifica datos de la empresa) y, opcionalmente, un dictamen de
     * verificación para el período.
     */
    public function store(Request $request, Company $company, Period $period)
    {
        $this->abortIfPeriodNotInCompany($period, $company);
        $this->authorizeAccess($company, $period);

        $data = $request->validate([
            'body' => 'required|string',
            'verdict' => 'nullable|string|in:conforme,no_conforme,observado',
        ]);

        $observation = $period->auditObservations()->create($data + [
            'company_id' => $company->id,
            'user_id' => Auth::id(),
        ]);

        return response()->json($observation->load('user:id,name,role'), 201);
    }

    /**
     * DELETE /companies/{company}/periods/{period}/observations/{observation}
     * Moderación: solo Superadmin/Admin pueden retirar una observación
     * (el Auditor solo tiene permiso de creación, no de edición/eliminación).
     */
    public function destroy(Company $company, Period $period, AuditObservation $observation)
    {
        $this->abortIfPeriodNotInCompany($period, $company);
        abort_unless($observation->period_id === $period->id, 404);

        $user = Auth::user();
        abort_unless(in_array($user->role, ['superadmin', 'admin']), 403);

        if ($user->role === 'admin') {
            $this->authorizeAccess($company, $period);
        }

        $observation->delete();

        return response()->json(null, 204);
    }

    private function abortIfPeriodNotInCompany(Period $period, Company $company): void
    {
        abort_unless($period->company_id === $company->id, 404);
    }

    /**
     * Superadmin: acceso total. Admin: solo su propia empresa. Auditor: solo el
     * período exacto para el que el Superadmin le otorgó un AuditorAssignment
     * vigente — company_user.expires_at (P1) controla si puede entrar al
     * contexto de la empresa; esto controla a qué período dentro de ella.
     */
    private function authorizeAccess(Company $company, Period $period): void
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
            $assigned = AuditorAssignment::where('user_id', $user->id)
                ->where('period_id', $period->id)
                ->active()
                ->exists();

            abort_unless($assigned, 403, 'No tienes autorización de auditoría para este período.');
            return;
        }

        abort(403);
    }
}
