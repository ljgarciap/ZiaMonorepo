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
        })->with('companies')->withTrashed()->get();

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
                $existingUser->companies()->syncWithoutDetaching($companies);
            }

            return response()->json($existingUser->load('companies'), 200);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|in:superadmin,admin,user,iot_tech,auditor',
            'companies' => 'array',
            'companies.*' => 'exists:companies,id',
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
            $user->companies()->sync($companies);
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
        // Enforce Role Restrictions
        $currentUser = auth()->user();
        $activeRole = $request->header('X-Context-Role') ?: $currentUser->role;

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
            $user->companies()->sync($companies);
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
}
