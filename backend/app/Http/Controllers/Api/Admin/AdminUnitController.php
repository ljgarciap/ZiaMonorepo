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
            'name'   => 'required|string|max:255',
            'symbol' => 'required|string|max:50|unique:measurement_units,symbol',
        ]);

        $unit = MeasurementUnit::create(array_merge($validated, ['is_standard' => false, 'is_active' => true]));
        return response()->json($unit, 201);
    }

    public function update(Request $request, MeasurementUnit $unit)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'symbol' => 'required|string|max:50|unique:measurement_units,symbol,' . $unit->id,
        ]);

        $unit->update($validated);
        return response()->json($unit);
    }

    public function destroy(MeasurementUnit $unit)
    {
        if ($unit->is_standard) {
            return response()->json(['message' => 'No se pueden eliminar unidades estándar GHG Protocol.'], 403);
        }

        if ($unit->factors()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar: esta unidad está en uso por factores de emisión.'], 409);
        }

        $unit->delete();
        return response()->json(null, 204);
    }

    public function toggle(MeasurementUnit $unit)
    {
        if ($unit->is_standard) {
            return response()->json(['message' => 'Las unidades estándar siempre están activas.'], 403);
        }
        $unit->update(['is_active' => !$unit->is_active]);
        return response()->json($unit);
    }
}
