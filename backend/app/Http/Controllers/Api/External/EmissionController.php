<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\CarbonEmission;
use Illuminate\Http\Request;

class EmissionController extends Controller
{
    /**
     * GET /api/external/v1/emissions
     * Emisiones de carbono de la empresa dueña de la API key usada — el
     * scope de empresa sale de la key resuelta por ApiKeyAuth, nunca de un
     * parámetro del request.
     */
    public function index(Request $request)
    {
        $companyId = $request->attributes->get('api_key')->company_id;

        $validated = $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
            'emission_factor_id' => 'nullable|integer|exists:emission_factors,id',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $emissions = CarbonEmission::query()
            ->whereHas('period', function ($q) use ($companyId, $validated) {
                $q->where('company_id', $companyId);
                if ($validated['year'] ?? null) {
                    $q->where('year', $validated['year']);
                }
            })
            ->when($validated['emission_factor_id'] ?? null, fn ($q, $factorId) => $q->where('emission_factor_id', $factorId))
            ->with(['period:id,year,status', 'factor:id,name'])
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 50);

        return response()->json($emissions);
    }
}
