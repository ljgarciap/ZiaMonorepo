<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AuthorizesCompanyAccess;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiKeyController extends Controller
{
    use AuthorizesCompanyAccess;

    /**
     * GET /admin/companies/{company}/api-keys
     * Nunca expone key_hash (oculto en el modelo) ni la key en texto plano —
     * solo se puede ver completa una vez, al crearla.
     */
    public function index(Company $company)
    {
        $this->authorizeCompanyAccess($company);

        return response()->json(
            $company->apiKeys()->with('creator:id,name')->orderByDesc('created_at')->get()
        );
    }

    /**
     * POST /admin/companies/{company}/api-keys
     * Devuelve la key en texto plano SOLO en esta respuesta — no se puede
     * recuperar después, ni siquiera por un superadmin (mismo patrón que un
     * token de Sanctum/GitHub/Stripe).
     */
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompanyAccess($company);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $result = ApiKey::generateFor($company, $data['name'], Auth::id());

        return response()->json([
            'key' => $result['key'],
            'warning' => 'Guardá esta key ahora — no se puede volver a mostrar.',
            'api_key' => $result['model'],
        ], 201);
    }

    /**
     * DELETE /admin/api-keys/{apiKey}
     * Revoca (no borra) — queda el registro para auditoría, pero deja de
     * autenticar de inmediato.
     */
    public function destroy(ApiKey $apiKey)
    {
        $this->authorizeCompanyAccess($apiKey->company);

        $apiKey->update(['revoked_at' => now()]);

        return response()->json(null, 204);
    }
}
