<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeRole = $request->header('X-Context-Role') ?: $user->role;

        $companies = $activeRole === 'superadmin'
            ? Company::with('sector')->get()
            : $user->companies()->with('sector')->wherePivot('is_active', true)->get();

        return response()->json($companies);
    }

    public function periods(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();
        $activeRole = $request->header('X-Context-Role') ?: $user->role;

        $hasAccess = $activeRole === 'superadmin'
            || $user->companies()->where('companies.id', $company->id)->exists();

        if (!$hasAccess) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(
            $company->periods()->orderBy('year', 'desc')->get()
        );
    }
}
