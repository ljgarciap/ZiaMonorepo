<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
            'factor_total_co2e' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
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
}
