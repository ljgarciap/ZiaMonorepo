<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = auth()->user();

        $requestedRole = $request->header('X-Context-Role');

        if ($requestedRole) {
            if (!$this->userHoldsRole($user, $requestedRole, $request->header('X-Company-ID'))) {
                return response()->json(['message' => 'Forbidden: Invalid role context.'], 403);
            }
            $activeRole = $requestedRole;
        } else {
            $activeRole = $user->role;
        }

        if (in_array($activeRole, $roles)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden: You do not have the required role.'], 403);
    }

    /**
     * Whether $user is actually entitled to act as $requestedRole, either as their
     * base account role, a superadmin self-downgrade to admin, or a per-company
     * pivot role. Prevents a spoofed X-Context-Role header from granting
     * privileges the user doesn't hold.
     *
     * When a company is named (X-Company-ID), the check always goes through that
     * company's pivot row, so a role tied to one company (e.g. Auditor externo,
     * which carries an expiration date) can't be granted just because the caller's
     * base account role happens to match — an expired pivot must fail even if
     * $user->role is still 'auditor'.
     */
    private function userHoldsRole($user, string $requestedRole, ?string $companyId): bool
    {
        if ($companyId) {
            return $this->activePivotQuery($user)
                ->where('companies.id', $companyId)
                ->wherePivot('role', $requestedRole)
                ->exists();
        }

        if ($requestedRole === $user->role) {
            return true;
        }

        if ($user->role === 'superadmin' && $requestedRole === 'admin') {
            return true;
        }

        return $this->activePivotQuery($user)->wherePivot('role', $requestedRole)->exists();
    }

    private function activePivotQuery($user)
    {
        return $user->companies()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('company_user.expires_at')
                    ->orWhere('company_user.expires_at', '>', now());
            });
    }
}
