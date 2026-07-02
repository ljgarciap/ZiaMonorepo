<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\EmissionCategory;
use App\Models\EmissionFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminMasterDataController extends Controller
{
// Categories CRUD
    public function indexCategories()
    {
        return response()->json(EmissionCategory::withTrashed()->with(['factors.unit', 'scope'])->get());
    }

    public function storeCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'scope_id' => 'required|exists:scopes,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $category = EmissionCategory::create($request->only(['name', 'scope_id', 'description']));
        return response()->json($category, 201);
    }

    public function deleteCategory(EmissionCategory $category)
    {
        $category->delete();
        return response()->json(null, 204);
    }
// ...
    // Factors CRUD
    public function storeFactor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emission_category_id' => 'required|exists:emission_categories,id',
            'name' => 'required|string|max:255',
            'measurement_unit_id' => 'required|exists:measurement_units,id',
            'factor_total_co2e' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // A05: bloquear factores con todos los gases en cero (sin justificación)
        $gasValues = array_filter([
            $request->input('factor_co2', 0),
            $request->input('factor_ch4', 0),
            $request->input('factor_n2o', 0),
            $request->input('factor_total_co2e', 0),
        ], fn($v) => floatval($v) > 0);

        if (empty($gasValues)) {
            return response()->json([
                'error' => 'El factor no puede tener todos los valores de gases en cero. Complete al menos un valor de emisión antes de guardar.',
            ], 422);
        }

        $factor = EmissionFactor::create($request->only([
            'emission_category_id', 'calculation_formula_id', 'measurement_unit_id',
            'name', 'factor_co2', 'factor_ch4', 'factor_n2o', 'factor_nf3', 'factor_sf6',
            'factor_total_co2e', 'uncertainty_lower', 'uncertainty_upper',
            'uncertainty_distribution', 'source_reference',
        ]));
        return response()->json($factor, 201);
    }

    public function updateFactor(Request $request, EmissionFactor $factor)
    {
        // A05: bloquear actualización si todos los gases quedan en cero
        $co2  = floatval($request->input('factor_co2',  $factor->factor_co2));
        $ch4  = floatval($request->input('factor_ch4',  $factor->factor_ch4));
        $n2o  = floatval($request->input('factor_n2o',  $factor->factor_n2o));
        $tot  = floatval($request->input('factor_total_co2e', $factor->factor_total_co2e));

        if ($co2 === 0.0 && $ch4 === 0.0 && $n2o === 0.0 && $tot === 0.0) {
            return response()->json([
                'error' => 'No se puede guardar un factor con todos los valores de gases en cero.',
            ], 422);
        }

        $factor->update($request->only([
            'emission_category_id', 'calculation_formula_id', 'measurement_unit_id',
            'name', 'factor_co2', 'factor_ch4', 'factor_n2o', 'factor_nf3', 'factor_sf6',
            'factor_total_co2e', 'uncertainty_lower', 'uncertainty_upper',
            'uncertainty_distribution', 'source_reference',
        ]));
        return response()->json($factor);
    }

    public function deleteFactor(EmissionFactor $factor)
    {
        $factor->delete();
        return response()->json(null, 204);
    }

    /**
     * GET /admin/factors/{factor}/versions
     * Spec 1.2.3: Superadmin "Configurar y versionar Factores de Emisión". El cálculo
     * de huella siempre usa el valor vigente de EmissionFactor (sin cambios de
     * comportamiento); esto solo expone el historial ya capturado por LogsActivity
     * como una línea de tiempo de versiones, para trazabilidad metodológica.
     */
    public function factorVersions(EmissionFactor $factor)
    {
        $history = ActivityLog::where('model', EmissionFactor::class)
            ->where('model_id', $factor->id)
            ->whereIn('action', ['created', 'updated'])
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->values()
            ->map(function ($log, $index) {
                return [
                    'version' => $index + 1,
                    'action' => $log->action,
                    'changed_by' => $log->user?->name,
                    'changed_at' => $log->created_at,
                    'changes' => $log->details,
                ];
            });

        return response()->json([
            'factor' => $factor,
            'versions' => $history,
        ]);
    }
}
