<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementUnit;
use Illuminate\Http\Request;

class AdminUnitController extends Controller
{
    public function index()
    {
        return response()->json(MeasurementUnit::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:50|unique:measurement_units,symbol',
        ]);

        $unit = MeasurementUnit::create($validated);
        return response()->json($unit, 201);
    }

    public function update(Request $request, MeasurementUnit $unit)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:50|unique:measurement_units,symbol,' . $unit->id,
        ]);

        $unit->update($validated);
        return response()->json($unit);
    }

    public function destroy(MeasurementUnit $unit)
    {
        // Check if unit is used in factors?
        if ($unit->factors()->count() > 0) {
            return response()->json(['message' => 'Cannot delete unit as it is used by emission factors.'], 409);
        }

        $unit->delete();
        return response()->json(null, 204);
    }
}
