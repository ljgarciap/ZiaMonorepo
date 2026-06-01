<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySector;
use Illuminate\Http\Request;

class CompanySectorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/sectors",
     *     summary="List all company sectors",
     *     tags={"Admin - Sectors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of sectors",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function index()
    {
        return response()->json(CompanySector::all());
    }

    /**
     * @OA\Post(
     *     path="/api/admin/sectors",
     *     summary="Create a new company sector",
     *     tags={"Admin - Sectors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Industrial"),
     *             @OA\Property(property="description", type="string", example="Industrial and manufacturing sector")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:company_sectors',
            'description' => 'nullable|string'
        ]);

        $sector = CompanySector::create($validated);
        return response()->json($sector, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/sectors/{sector}",
     *     summary="Get sector details",
     *     tags={"Admin - Sectors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="sector", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Sector details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(CompanySector $sector)
    {
        return response()->json($sector);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/sectors/{sector}",
     *     summary="Update a company sector",
     *     tags={"Admin - Sectors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="sector", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Industrial Updated"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, CompanySector $sector)
    {
        $validated = $request->validate([
            'name' => 'string|max:255|unique:company_sectors,name,' . $sector->id,
            'description' => 'nullable|string'
        ]);

        $sector->update($validated);
        return response()->json($sector);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/sectors/{sector}",
     *     summary="Delete a company sector",
     *     tags={"Admin - Sectors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="sector", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(CompanySector $sector)
    {
        $sector->delete();
        return response()->json(null, 204);
    }
}
