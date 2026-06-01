<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalculationFormula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CalculationFormulaController extends Controller
{
    public function index()
    {
        return response()->json(CalculationFormula::all());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:calculation_formulas',
            'expression' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $formula = CalculationFormula::create($request->all());
        return response()->json($formula, 201);
    }

    public function show(CalculationFormula $formula)
    {
        return response()->json($formula);
    }

    public function update(Request $request, CalculationFormula $formula)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:calculation_formulas,name,' . $formula->id,
            'expression' => 'sometimes|required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $formula->update($request->all());
        return response()->json($formula);
    }

    public function destroy(CalculationFormula $formula)
    {
        $formula->delete();
        return response()->json(null, 204);
    }
}
