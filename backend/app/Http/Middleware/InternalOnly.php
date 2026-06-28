<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.internal_secret');

        if (!$secret || $request->header('X-Internal-Secret') !== $secret) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
