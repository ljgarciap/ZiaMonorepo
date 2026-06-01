<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbonEmission;
use App\Models\EmissionFactor;
use App\Models\Period;
use App\Services\CarbonFootprintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarbonEmissionController extends Controller
{
    protected $carbonService;

    public function __construct(CarbonFootprintService $carbonService)
    {
        $this->carbonService = $carbonService;
    }

    /**
     * @OA\Post(
     *     path="/api/periods/{period}/emissions",
     *     summary="Calculate and store a new carbon emission record",
     *     tags={"Emissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="path",
     *         required=true,
     *         description="The ID of the period",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"emission_factor_id"},
     *             @OA\Property(property="emission_factor_id", type="integer", example=1),
     *             @OA\Property(property="quantity", type="number", format="float", example=100.0, description="Total activity data if monthly_inputs not provided"),
     *             @OA\Property(property="monthly_inputs", type="array", @OA\Items(type="number", format="float"), example={10, 20, 30}, description="Array of monthly values for uncertainty analysis"),
     *             @OA\Property(property="notes", type="string", example="Monthly fuel consumption")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Emission record created and calculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="calculated_co2e", type="number", format="float", example=5.67),
     *             @OA\Property(property="uncertainty_result", type="number", format="float", example=0.25)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, Period $period)
    {
        $validated = $request->validate([
            'emission_factor_id' => 'required|exists:emission_factors,id',
            'quantity' => 'required_without:monthly_inputs|numeric|min:0',
            'monthly_inputs' => 'array', // Optional, for uncertainty calculation
            'notes' => 'nullable|string'
        ]);

        $factor = EmissionFactor::with('formula')->findOrFail($validated['emission_factor_id']);
        
        // Prepare inputs for calculation
        if (isset($validated['monthly_inputs']) && is_array($validated['monthly_inputs'])) {
            $inputs = $validated['monthly_inputs'];
        } else {
            $inputs = [$validated['quantity']];
        }
        
        // Ensure inputs are numeric
        $inputs = array_map('floatval', $inputs);

        // Calculate
        $results = $this->carbonService->calculate($inputs, $factor);

        // Create Record
        $emission = $period->emissions()->create([
            'emission_factor_id' => $factor->id,
            'quantity' => $results['activity_data_total'],
            'emissions_co2' => $results['emissions_co2'],
            'emissions_ch4' => $results['emissions_ch4'],
            'emissions_n2o' => $results['emissions_n2o'],
            'calculated_co2e' => $results['calculated_co2e'],
            'uncertainty_result' => $results['uncertainty_result'],
            'activity_data_total' => $results['activity_data_total'],
            'activity_data_stdev' => $results['activity_data_stdev'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($emission, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/periods/{period}/emissions",
     *     summary="List all emissions for a specific period",
     *     tags={"Emissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of emissions",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function index(Period $period)
    {
        return response()->json($period->emissions()->with('factor')->get());
    }
    
    /**
     * Update an emission record.
     */
     public function update(Request $request, CarbonEmission $emission)
     {
         // Similar logic to store, recalculating values.
         // For MVP, implementing store is critical.
         // ...
         return response()->json(['message' => 'Update not implemented in this step'], 501);
     }
     
     /**
     * @OA\Delete(
     *     path="/api/emissions/{emission}",
     *     summary="Delete an emission record",
     *     tags={"Emissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="emission",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Deleted successfully")
     * )
     */
    public function destroy(CarbonEmission $emission)
    {
        $emission->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/companies/{company}/emissions/history",
     *     summary="Get paginated emission history for a company",
     *     tags={"Emissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_dir", in="query", @OA\Schema(type="string", enum={"asc", "desc"}))
     * )
     */
    public function history(Request $request, \App\Models\Company $company)
    {
        $query = CarbonEmission::query()
            ->select('carbon_emissions.*')
            ->join('periods', 'carbon_emissions.period_id', '=', 'periods.id')
            ->where('periods.company_id', $company->id)
            ->with(['period', 'factor.category.scope', 'factor.unit']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->whereHas('factor', function($f) use ($search) {
                    $f->where('name', 'like', "%{$search}%")
                      ->orWhereHas('category', function($c) use ($search) {
                          $c->where('name', 'like', "%{$search}%")
                            ->orWhereHas('scope', function($s) use ($search) {
                                $s->where('name', 'like', "%{$search}%");
                            });
                      });
                })
                ->orWhere('carbon_emissions.notes', 'like', "%{$search}%")
                ->orWhere('periods.year', 'like', "%{$search}%");
            });
        }

        // Sorting
        $allowedSorts = ['created_at', 'calculated_co2e', 'quantity', 'period_year'];
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'period_year') {
                $query->orderBy('periods.year', $sortDir);
            } else {
                $query->orderBy('carbon_emissions.' . $sortBy, $sortDir);
            }
        } else {
            $query->orderBy('carbon_emissions.created_at', 'desc');
        }

        $perPage = $request->input('per_page', 10);
        return response()->json($query->paginate($perPage));
    }
}
