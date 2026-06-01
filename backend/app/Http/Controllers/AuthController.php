<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('authToken')->accessToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Login to get access token",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@zia.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful login",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('authToken')->accessToken;

            // Load associated companies
            $user->load('companies');

            $contexts = [];

            // 1. Global Contexts
            if ($user->role === 'superadmin') {
                $contexts[] = ['type' => 'global', 'role' => 'superadmin', 'label' => 'Portal de Superadministrador'];
                $contexts[] = ['type' => 'global', 'role' => 'admin', 'label' => 'Portal de Administrador (Restringido)'];
            } elseif ($user->role === 'admin') {
                $contexts[] = ['type' => 'global', 'role' => 'admin', 'label' => 'Portal de Administrador'];
            }

            // 2. Company Contexts
            foreach ($user->companies as $company) {
                 if ($company->pivot->is_active) {
                    $contexts[] = [
                        'type' => 'company',
                        'id' => $company->id,
                        'name' => $company->name,
                        'role' => $company->pivot->role,
                        'label' => $company->name,
                        'logo_url' => $company->logo_url
                    ];
                 }
            }

            // 3. Response Logic
            if (count($contexts) === 0) {
                 return response()->json(['error' => 'No active roles assigned.'], 403);
            }

            if (count($contexts) === 1) {
                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'context' => $contexts[0],
                    'require_selection' => false
                ], 200);
            }

            return response()->json([
                'user' => $user,
                'token' => $token,
                'contexts' => $contexts,
                'require_selection' => true
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout the current user",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * @OA\Get(
     *     path="/api/user",
     *     summary="Get the authenticated user",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current user details"
     *     )
     * )
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
