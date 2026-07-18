<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Autentica a un consumidor externo por header X-Api-Key. La empresa se
     * resuelve SIEMPRE desde la key encontrada en BD — nunca desde un header
     * o parámetro que el cliente pueda enviar — así un tercero no puede pedir
     * datos de una empresa que no sea la suya, ni por error de integración ni
     * a propósito.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainKey = $request->header('X-Api-Key');

        if (!$plainKey) {
            return response()->json(['error' => 'Falta el header X-Api-Key.'], 401);
        }

        $apiKey = ApiKey::with('company')
            ->where('key_hash', ApiKey::hash($plainKey))
            ->whereNull('revoked_at')
            ->first();

        // company() es un belongsTo — Eloquent ya excluye empresas
        // soft-deleted por el scope global de SoftDeletes, así que si la
        // empresa fue borrada, $apiKey->company viene null acá sin
        // necesidad de un chequeo explícito de trashed(). Sin esto, borrar
        // una empresa no revocaba las keys que ya había emitido.
        if (!$apiKey || !$apiKey->company) {
            return response()->json(['error' => 'API key inválida o revocada.'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
