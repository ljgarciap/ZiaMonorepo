<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditorAssignment;
use App\Models\Period;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuditorAssignmentController extends Controller
{
    /**
     * GET /admin/auditor-assignments
     * Superadmin: quién tiene acceso a qué empresa+período como Auditor externo.
     */
    public function index(Request $request)
    {
        $query = AuditorAssignment::with(['user:id,name,email', 'company:id,name', 'period:id,year', 'grantedBy:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return response()->json($query->get());
    }

    /**
     * POST /admin/auditor-assignments
     * Habilita a un Auditor externo para un período específico de una empresa,
     * con vencimiento opcional (spec 1.2.3: "acceso temporal... duración limitada").
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_id' => 'required|exists:periods,id',
            'expires_at' => 'nullable|date',
        ]);

        $auditor = User::findOrFail($data['user_id']);
        if ($auditor->role !== 'auditor') {
            return response()->json(['message' => 'El usuario asignado debe tener rol auditor.'], 422);
        }

        $period = Period::findOrFail($data['period_id']);

        $assignment = AuditorAssignment::updateOrCreate(
            ['user_id' => $auditor->id, 'period_id' => $period->id],
            [
                'company_id' => $period->company_id,
                'granted_by' => Auth::id(),
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        return response()->json($assignment->load(['user:id,name,email', 'company:id,name', 'period:id,year']), 201);
    }

    /**
     * DELETE /admin/auditor-assignments/{assignment}
     * Revoca el acceso del Auditor a ese período (revocación manual, además de
     * la expiración automática por expires_at).
     */
    public function destroy(AuditorAssignment $assignment)
    {
        $assignment->delete();
        return response()->json(null, 204);
    }
}
