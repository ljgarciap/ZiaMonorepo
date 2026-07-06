<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AssertsCompanyAccess;
use App\Models\Company;
use App\Models\OperationalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOperationalUnitController extends Controller
{
    use AssertsCompanyAccess;

    public function index(Request $request, Company $company)
    {
        if ($error = $this->assertAccess($request, $company)) {
            return $error;
        }

        return response()->json(
            $company->operationalUnits()->withCount('users')->get()
        );
    }

    public function store(Request $request, Company $company)
    {
        if ($error = $this->assertAccess($request, $company)) {
            return $error;
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $unit = $company->operationalUnits()->create($request->only('name', 'description'));

        return response()->json($unit, 201);
    }

    public function update(Request $request, Company $company, OperationalUnit $unit)
    {
        if ($error = $this->assertAccess($request, $company)) {
            return $error;
        }
        $this->abortIfNotBelongs($unit, $company);

        $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $unit->update($request->only('name', 'description'));

        return response()->json($unit);
    }

    public function destroy(Request $request, Company $company, OperationalUnit $unit)
    {
        if ($error = $this->assertAccess($request, $company)) {
            return $error;
        }
        $this->abortIfNotBelongs($unit, $company);
        $unit->delete();
        return response()->json(null, 204);
    }

    // Assign a user to a unit within a company
    public function assignUser(Request $request, Company $company, OperationalUnit $unit)
    {
        if ($error = $this->assertAccess($request, $company)) {
            return $error;
        }
        $this->abortIfNotBelongs($unit, $company);

        $request->validate(['user_id' => 'required|exists:users,id']);

        DB::table('company_user')
            ->where('user_id', $request->user_id)
            ->where('company_id', $company->id)
            ->update(['unit_id' => $unit->id]);

        return response()->json(['message' => 'Usuario asignado a unidad.']);
    }

    // Remove unit assignment (user stays in company, unit becomes null)
    public function unassignUser(Request $request, Company $company, OperationalUnit $unit)
    {
        if ($error = $this->assertAccess($request, $company)) {
            return $error;
        }
        $this->abortIfNotBelongs($unit, $company);

        $request->validate(['user_id' => 'required|exists:users,id']);

        DB::table('company_user')
            ->where('user_id', $request->user_id)
            ->where('company_id', $company->id)
            ->where('unit_id', $unit->id)
            ->update(['unit_id' => null]);

        return response()->json(['message' => 'Asignación de unidad removida.']);
    }

    private function assertAccess(Request $request, Company $company): ?JsonResponse
    {
        $user = $request->user();
        $activeRole = $request->header('X-Context-Role') ?: $user->role;
        return $this->assertCompanyPeriodAccess($user, $activeRole, $company);
    }

    private function abortIfNotBelongs(OperationalUnit $unit, Company $company): void
    {
        abort_unless($unit->company_id === $company->id, 404);
    }
}
