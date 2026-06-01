<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class ContextAwareMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->header('X-Company-ID');
        $contextRole = $request->header('X-Context-Role');

        if ($companyId) {
            $user = $request->user(); // Or Auth::user()

            if (!$user) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }

            // Check if user has access to this company
            $company = $user->companies()->where('companies.id', $companyId)->first();

            if (!$company && !$user->isGlobalAdmin()) {
                return response()->json(['error' => 'Unauthorized access to this company context.'], 403);
            }

            // If context role is provided, validate it matches the pivot or allowed roles
            if ($contextRole) {
                if ($company && $company->pivot->role !== $contextRole && !$user->isGlobalAdmin()) {
                     return response()->json(['error' => 'Invalid role for this context.'], 403);
                }
            }
            
            // Set context in request attributes for controllers to use
            $request->attributes->add(['current_company_id' => $companyId]);
            $request->attributes->add(['current_role' => $contextRole ?: ($company ? $company->pivot->role : $user->role)]);
        }

        return $next($request);
    }
}
