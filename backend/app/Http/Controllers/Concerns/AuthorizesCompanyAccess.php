<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;

/**
 * Chequeo simple de "¿este usuario administra esta empresa?" — superadmin
 * sin restricción, cualquier otro rol debe estar en el pivote company_user
 * con is_active. Distinto de AssertsCompanyAccess (que además resuelve un
 * $activeRole de contexto y variantes por rol como Auditor); este es el
 * criterio más simple que ya se repetía igual en IotDeviceController y
 * ApiKeyController antes de extraerlo acá.
 */
trait AuthorizesCompanyAccess
{
    protected function authorizeCompanyAccess(?Company $company): void
    {
        abort_if(!$company, 404);

        $user = Auth::user();

        if ($user->role === 'superadmin') {
            return;
        }

        $belongs = $user->companies()
            ->where('companies.id', $company->id)
            ->wherePivot('is_active', true)
            ->exists();

        abort_unless($belongs, 403, 'No tienes acceso a esta empresa.');
    }
}
