<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeCredentials;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $activeRole = request()->header('X-Context-Role') ?: $user->role;
        if ($activeRole === 'superadmin') {
            return response()->json(User::with('companies')->withTrashed()->get());
        }

        $companyIds = $user->companies->pluck('id');
        $users = User::whereHas('companies', function($query) use ($companyIds) {
            $query->whereIn('companies.id', $companyIds);
        })
        ->whereNotIn('role', ['superadmin', 'admin']) // A08: admin no ve roles iguales o superiores
        ->with('companies')->withTrashed()->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        // Check if user already exists in the system (including soft deleted)
        $existingUser = User::withTrashed()->where('email', $request->email)->first();
        $currentUser = auth()->user();
        $activeRole = $request->header('X-Context-Role') ?: $currentUser->role;

        if ($existingUser) {
            if ($activeRole === 'admin' && $request->role !== 'user') {
                 return response()->json(['error' => 'Admins can only manage Users, not other Admins or Superadmins.'], 403);
            }

            if ($existingUser->trashed()) {
                $existingUser->restore();
            }

            if ($request->has('companies')) {
                $companies = $request->companies;
                if ($activeRole === 'admin') {
                    $myCompanyIds = $currentUser->companies->pluck('id')->toArray();
                    $companies = array_intersect($companies, $myCompanyIds);
                }
                $existingUser->companies()->syncWithoutDetaching(
                    $this->pivotData($companies, $existingUser->role, $request->access_expires_at)
                );
            }

            return response()->json($existingUser->load('companies'), 200);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|in:superadmin,admin,user,iot_tech,auditor,viewer',
            'companies' => 'array',
            'companies.*' => 'exists:companies,id',
            'access_expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Enforce Role Restrictions
        if ($activeRole === 'admin' && $request->role !== 'user') {
             return response()->json(['error' => 'Admins can only create Users, not other Admins or Superadmins.'], 403);
        }

        $password = $request->password ?: 'password';

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => Hash::make($password),
        ]);

        if ($request->has('companies')) {
            $companies = $request->companies;
            if ($activeRole === 'admin') {
                $myCompanyIds = $currentUser->companies->pluck('id')->toArray();
                $companies = array_intersect($companies, $myCompanyIds);
            }
            $user->companies()->sync($this->pivotData($companies, $user->role, $request->access_expires_at));
        }

        try {
            Mail::to($user->email)->send(new WelcomeCredentials($user, $password));
        } catch (\Exception $e) {
            Log::warning("WelcomeCredentials mail failed for {$user->email}: " . $e->getMessage());
        }

        return response()->json($user->load('companies'), 201);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();
        $activeRole = $request->header('X-Context-Role') ?: $currentUser->role;

        // A08: admin no puede editar usuarios de rol igual o superior al suyo
        if ($activeRole === 'admin' && in_array($user->role, ['superadmin', 'admin'])) {
            return response()->json(['error' => 'No puedes editar usuarios con rol igual o superior al tuyo.'], 403);
        }

        if ($activeRole === 'admin' && $request->has('role') && $request->role !== 'user') {
             return response()->json(['error' => 'Admins can only update Users.'], 403);
        }
        
        $data = $request->only('name', 'email', 'role');
        if ($request->has('password') && !empty($request->password)) {
            $data['password'] = Hash::make($request->password);
        }
        
        $user->update($data);

        if ($request->has('companies')) {
            $companies = $request->companies;
            if ($activeRole === 'admin') {
                $myCompanyIds = $currentUser->companies->pluck('id')->toArray();
                $companies = array_intersect($companies, $myCompanyIds);
            }
            $user->companies()->sync($this->pivotData($companies, $user->role, $request->access_expires_at));
        }

        return response()->json($user->load('companies'));
    }

    public function destroy(User $user)
    {
        $activeRole = request()->header('X-Context-Role') ?: auth()->user()->role;
        if ($activeRole !== 'superadmin') {
            return response()->json(['error' => 'Solo el Superadmin puede eliminar usuarios.'], 403);
        }
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'Cannot delete yourself'], 400);
        }
        $user->delete();
        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return response()->json($user->load('companies'));
    }

    /**
     * POST /admin/users/{user}/toggle-block
     * Spec 1.2.3: Superadmin "Habilitar o bloquear... cuentas" — distinto de
     * eliminar: la cuenta sigue existiendo pero no puede autenticarse mientras
     * esté bloqueada (ver AuthController::login).
     */
    public function toggleBlock(User $user)
    {
        $activeRole = request()->header('X-Context-Role') ?: auth()->user()->role;
        if ($activeRole !== 'superadmin') {
            return response()->json(['error' => 'Solo el Superadmin puede bloquear cuentas.'], 403);
        }
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'No puedes bloquear tu propia cuenta.'], 400);
        }

        $user->update([
            'is_blocked' => !$user->is_blocked,
            'blocked_at' => $user->is_blocked ? null : now(),
        ]);

        return response()->json($user);
    }

    /**
     * Builds sync() pivot data keyed by company id. The pivot 'role' must mirror
     * the user's account role — otherwise a company-scoped context (used by
     * RoleMiddleware to validate X-Context-Role) silently falls back to the
     * column default ('user'), granting a broader role than the account has.
     */
    private function pivotData(array $companyIds, string $role, ?string $expiresAt): array
    {
        return collect($companyIds)->mapWithKeys(fn ($id) => [
            $id => ['role' => $role, 'expires_at' => $expiresAt],
        ])->all();
    }
}
