<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    public function index()
    {
        return response()->json(User::with('companies')->withTrashed()->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:superadmin,admin,user',
            'companies' => 'array',
            'companies.*' => 'exists:companies,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Enforce Role Restrictions
        $currentUser = auth()->user();
        if ($currentUser->role === 'admin' && $request->role !== 'user') {
             return response()->json(['error' => 'Admins can only create Users, not other Admins or Superadmins.'], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => Hash::make($request->password),
        ]);

        if ($request->has('companies')) {
            $user->companies()->sync($request->companies);
        }

        return response()->json($user->load('companies'), 201);
    }

    public function update(Request $request, User $user)
    {
        // Enforce Role Restrictions
        $currentUser = auth()->user();
        if ($currentUser->role === 'admin' && $request->has('role') && $request->role !== 'user') {
             return response()->json(['error' => 'Admins can only update Users.'], 403);
        }
        
        $data = $request->only('name', 'email', 'role');
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }
        
        $user->update($data);

        if ($request->has('companies')) {
            $user->companies()->sync($request->companies);
        }

        return response()->json($user->load('companies'));
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'Cannot delete yourself'], 400);
        }
        $user->delete();
        return response()->json(null, 204);
    }
}
