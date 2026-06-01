<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminCompanyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/companies",
     *     summary="List all companies with periods (Admin)",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of companies")
     * )
     */
    public function index()
    {
        return response()->json(Company::withTrashed()->with(['periods', 'sector'])->get());
    }

    /**
     * @OA\Post(
     *     path="/api/admin/companies",
     *     summary="Create a new company",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "nit"},
     *             @OA\Property(property="name", type="string", example="Acme Corp"),
     *             @OA\Property(property="nit", type="string", example="123456789-0"),
             *             @OA\Property(property="company_sector_id", type="integer", example=1),
     *             @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'nit' => 'nullable|string|max:20',
            'company_sector_id' => 'nullable|exists:company_sectors,id',
            'logo_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $company = Company::create($request->all());
        return response()->json($company, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/companies/{company}",
     *     summary="Update a company",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(Request $request, Company $company)
    {
        $company->update($request->all());
        return response()->json($company);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/companies/{company}",
     *     summary="Soft delete a company",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted")
     * )
     */
    public function destroy(Company $company)
    {
        $company->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/companies/{company}/periods",
     *     summary="Add a new period to a company",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"year", "status"},
     *             @OA\Property(property="year", type="integer", example=2024),
     *             @OA\Property(property="status", type="string", example="open")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Period added")
     * )
     */
    public function addPeriod(Request $request, Company $company)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer',
            'status' => 'required|string|in:open,closed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $period = $company->periods()->create($request->all());
        return response()->json($period, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/periods/{period}",
     *     summary="Update a period",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function updatePeriod(Request $request, Period $period)
    {
        $period->update($request->all());
        return response()->json($period);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/periods/{period}",
     *     summary="Delete a period",
     *     tags={"Admin - Companies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted")
     * )
     */
    public function deletePeriod(Period $period)
    {
        $period->delete();
        return response()->json(null, 204);
    }
}
