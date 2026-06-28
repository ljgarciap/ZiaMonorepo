<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyGroup;
use App\Models\CarbonEmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyGroupController extends Controller
{
    public function index()
    {
        $groups = CompanyGroup::with('companies:id,name,nit,company_sector_id')->get();
        return response()->json($groups);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'description'    => 'nullable|string',
            'company_ids'    => 'array',
            'company_ids.*'  => 'exists:companies,id',
        ]);

        $group = CompanyGroup::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by'  => auth()->id(),
        ]);

        if (!empty($validated['company_ids'])) {
            $group->companies()->attach($validated['company_ids']);
        }

        return response()->json($group->load('companies:id,name,nit'), 201);
    }

    public function addCompany(Request $request, CompanyGroup $group)
    {
        $request->validate(['company_id' => 'required|exists:companies,id']);
        $group->companies()->syncWithoutDetaching([$request->company_id]);
        return response()->json(['message' => 'Company added to group']);
    }

    public function removeCompany(Request $request, CompanyGroup $group)
    {
        $request->validate(['company_id' => 'required|exists:companies,id']);
        $group->companies()->detach($request->company_id);
        return response()->json(['message' => 'Company removed from group']);
    }

    /**
     * Consolidated emissions summary for all companies in the group.
     * GET /api/groups/{group}/summary?year={year}
     */
    public function summary(Request $request, CompanyGroup $group)
    {
        $year = $request->query('year');

        $companyIds = $group->companies()->pluck('companies.id');

        $query = CarbonEmission::query()
            ->select([
                'scopes.id as scope_id',
                'scopes.name as scope_name',
                DB::raw('SUM(carbon_emissions.calculated_co2e) as total_co2e'),
                DB::raw('COUNT(carbon_emissions.id) as records'),
            ])
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->join('emission_factors', 'carbon_emissions.emission_factor_id', '=', 'emission_factors.id')
            ->join('emission_categories', 'emission_factors.emission_category_id', '=', 'emission_categories.id')
            ->join('scopes', 'emission_categories.scope_id', '=', 'scopes.id')
            ->whereIn('periods.company_id', $companyIds);

        if ($year) {
            $query->where('periods.year', $year);
        }

        $byScope = $query->groupBy('scopes.id', 'scopes.name')->get();

        $byCompany = CarbonEmission::query()
            ->select([
                'companies.id as company_id',
                'companies.name as company_name',
                DB::raw('SUM(carbon_emissions.calculated_co2e) as total_co2e'),
            ])
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->join('companies', 'periods.company_id', '=', 'companies.id')
            ->whereIn('periods.company_id', $companyIds)
            ->when($year, fn($q) => $q->where('periods.year', $year))
            ->groupBy('companies.id', 'companies.name')
            ->get();

        return response()->json([
            'group'       => ['id' => $group->id, 'name' => $group->name],
            'year'        => $year,
            'total_co2e'  => $byScope->sum('total_co2e'),
            'by_scope'    => $byScope,
            'by_company'  => $byCompany,
        ]);
    }

    public function destroy(CompanyGroup $group)
    {
        $group->delete();
        return response()->json(null, 204);
    }
}
