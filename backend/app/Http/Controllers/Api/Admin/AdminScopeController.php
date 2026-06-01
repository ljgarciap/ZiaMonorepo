<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scope;
use Illuminate\Http\Request;

class AdminScopeController extends Controller
{
    public function index()
    {
        return response()->json(Scope::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'documentation_text' => 'nullable|string',
        ]);

        $scope = Scope::create($validated);
        return response()->json($scope, 201);
    }

    public function update(Request $request, Scope $scope)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'documentation_text' => 'nullable|string',
        ]);

        $scope->update($validated);
        return response()->json($scope);
    }

    public function destroy(Scope $scope)
    {
        // Prevent deleting core scopes if needed, or just let them delete.
        // Ideally we should check if it has categories/factors used.
        if ($scope->categories()->count() > 0) {
            return response()->json(['message' => 'Cannot delete scope with associated categories.'], 409);
        }

        $scope->delete();
        return response()->json(null, 204);
    }
}
