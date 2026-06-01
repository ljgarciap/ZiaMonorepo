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

        $category = EmissionCategory::create($request->all());
        return response()->json($category, 201);
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

        $factor = EmissionFactor::create($request->all());
        return response()->json($factor, 201);
    }

    public function updateFactor(Request $request, EmissionFactor $factor)
    {
        $factor->update($request->all());
        return response()->json($factor);
    }

    public function deleteFactor(EmissionFactor $factor)
    {
        $factor->delete();
        return response()->json(null, 204);
    }
}
