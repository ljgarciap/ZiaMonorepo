<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmissionFactor;
use Illuminate\Http\Request;

class AdminCompanyFactorController extends Controller
{
    /**
     * Get all factors with their enablement status for a specific company.
     */
    public function index(Company $company)
    {
        // Get all factors and mark those enabled for this company
        $allFactors = EmissionFactor::with(['category', 'unit'])->get();
        
        // Use an associative array (map) for O(1) lookups instead of in_array O(N)
        $enabledFactorsMap = $company->factors()
            ->wherePivot('is_enabled', true)
            ->pluck('is_enabled', 'emission_factors.id')
            ->toArray();

        $result = $allFactors->map(function ($factor) use ($enabledFactorsMap) {
            return [
                'id' => $factor->id,
                'name' => $factor->name,
                'category_name' => $factor->category->name ?? 'N/A',
                'unit_symbol' => $factor->unit->symbol ?? 'N/A',
                'is_enabled' => isset($enabledFactorsMap[$factor->id])
            ];
        });

        return response()->json($result);
    }

    /**
     * Partially update (toggle) which factors are enabled for a company.
     */
    public function update(Request $request, Company $company)
    {
        $request->validate([
            'factors' => 'required|array',
            'factors.*.id' => 'required|integer|exists:emission_factors,id',
            'factors.*.is_enabled' => 'required|boolean'
        ]);

        $syncData = [];
        foreach ($request->factors as $f) {
            $syncData[$f['id']] = ['is_enabled' => $f['is_enabled']];
        }

        $company->factors()->sync($syncData);

        return response()->json(['message' => 'Factores actualizados correctamente para ' . $company->name]);
    }
}
