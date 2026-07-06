<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AssertsCompanyAccess;
use App\Models\Company;
use App\Models\EmissionCategory;
use App\Models\SectorQuestionnaireRule;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    use AssertsCompanyAccess;


    /**
     * @OA\Get(
     *     path="/api/dictionaries/factors",
     *     summary="Get all emission factors grouped by category",
     *     description="Use this to populate dropdowns in the frontend. Includes nested factors for each category.",
     *     tags={"Master Data"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Grouped categories and factors",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function emissionFactors(Request $request)
    {
        $companyId = $request->query('company_id');

        if ($companyId && ($company = Company::find($companyId))) {
            $user = $request->user();
            $activeRole = $request->header('X-Context-Role') ?: $user->role;
            if ($error = $this->assertCompanyPeriodAccess($user, $activeRole, $company)) {
                return $error;
            }
        }

        // Return hierarchy: Scope -> Categories (root only) -> Children (recursive) -> Factors
        $scopes = \App\Models\Scope::with(['categories' => function($query) use ($companyId) {
            $query->whereNull('parent_id')
                  ->with(['children' => function($q) use ($companyId) {
                      $q->with(['factors' => function($fQuery) use ($companyId) {
                          $fQuery->with('unit', 'formula');
                          if ($companyId) {
                              $fQuery->with(['companies' => function($cq) use ($companyId) {
                                  $cq->where('company_id', $companyId);
                              }]);
                          }
                      }]); 
                  }, 'factors' => function($fQuery) use ($companyId) {
                      $fQuery->with('unit', 'formula');
                      if ($companyId) {
                          $fQuery->with(['companies' => function($cq) use ($companyId) {
                              $cq->where('company_id', $companyId);
                          }]);
                      }
                  }])
                  ->orderBy('id');
        }])->get();

        // Recursively filter factors in PHP to avoid expensive correlated subqueries
        $scopes->each(function($scope) use ($companyId) {
            $scope->categories->each(function($cat) use ($companyId) {
                $this->filterCategoryFactors($cat, $companyId);
            });
        });

        return response()->json($scopes);
    }

    private function filterCategoryFactors($category, $companyId)
    {
        if ($companyId) {
            $category->setRelation('factors', $category->factors->filter(function($factor) {
                $pivot = $factor->companies->first();
                // If no record, enabled by default. If record exists, check is_enabled.
                return !$pivot || $pivot->pivot->is_enabled;
            })->values());
        }

        if ($category->children) {
            $category->children->each(function($child) use ($companyId) {
                $this->filterCategoryFactors($child, $companyId);
            });
        }
    }

    /**
     * Returns questionnaire questions for a sector, with correct emission_factor_id per question.
     * GET /api/dictionaries/questionnaire?sector={code}
     */
    public function questionnaireRules(Request $request)
    {
        $sectorCode = $request->query('sector');
        $companyId  = $request->query('company_id');

        if (!$sectorCode) {
            return response()->json(['error' => 'sector query parameter is required'], 422);
        }

        if ($companyId && ($company = Company::find($companyId))) {
            $user = $request->user();
            $activeRole = $request->header('X-Context-Role') ?: $user->role;
            if ($error = $this->assertCompanyPeriodAccess($user, $activeRole, $company)) {
                return $error;
            }
        }

        $rules = SectorQuestionnaireRule::with([
                'emissionFactor.unit',
                'emissionFactor.category.scope',
            ])
            ->where('sector_code', $sectorCode)
            ->when($companyId, function ($q) use ($companyId) {
                // Exclude factors explicitly disabled for this company
                $q->whereDoesntHave('emissionFactor.companies', function ($cq) use ($companyId) {
                    $cq->where('company_emission_factor.company_id', $companyId)
                       ->where('company_emission_factor.is_enabled', false);
                });
            })
            ->orderBy('display_order')
            ->get()
            ->map(fn($rule) => [
                'id'                  => $rule->id,
                'emission_factor_id'  => $rule->emission_factor_id,
                'questionnaire_label' => $rule->questionnaire_label,
                'variable_name'       => $rule->variable_name,
                'input_unit_hint'     => $rule->input_unit_hint,
                'is_required'         => (bool) $rule->is_required,
                'display_order'       => $rule->display_order,
                'help_text'           => $rule->help_text,
                'factor_name'         => $rule->emissionFactor?->name,
                'factor_total_co2e'   => $rule->emissionFactor?->factor_total_co2e,
                'unit_symbol'         => $rule->emissionFactor?->unit?->symbol,
                'scope_id'            => $rule->emissionFactor?->category?->scope_id,
                'scope_name'          => $rule->emissionFactor?->category?->scope?->name,
            ]);

        return response()->json($rules);
    }
}
