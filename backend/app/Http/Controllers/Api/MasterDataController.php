<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmissionCategory;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
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
}
