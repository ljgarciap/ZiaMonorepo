<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $activeRole = $request->header('X-Context-Role') ?: $user->role;

        $query = ActivityLog::with('user:id,name,email,role')->orderBy('created_at', 'desc');

        if ($activeRole === 'superadmin') {
            // Superadmin: bitácora global completa (sin restricción de tenant)
        } elseif ($activeRole === 'admin') {
            // Admin: bitácora acotada a usuarios de sus empresas (Matriz v2: Bitácora de empresa = R)
            $companyIds = $user->companies->pluck('id');
            $tenantUserIds = DB::table('company_user')
                ->whereIn('company_id', $companyIds)
                ->pluck('user_id')
                ->unique();
            $query->whereIn('user_id', $tenantUserIds);
        } else {
            return response()->json(['message' => 'Forbidden: insufficient role.'], 403);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('model')) {
            $query->where('model', 'like', '%' . $request->model . '%');
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json($query->paginate(20));
    }
}
