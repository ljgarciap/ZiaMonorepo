<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Scramble's docs/api UI is 403'd outside the 'local' env unless this
        // gate allows it — documentación de solo lectura (schema, no datos),
        // se deja abierta para que el equipo y auditoría externa la consulten.
        Gate::define('viewApiDocs', fn (?\App\Models\User $user = null) => true);

        // API externa (terceros con API key, no usuarios Zia): límite por key,
        // no por IP — varias integraciones de un mismo tercero pueden compartir
        // salida de red, y una key revocada/inválida no debe poder agotar el
        // límite de nadie más (ApiKeyAuth corre antes, así que $request->attributes
        // ya tiene la key resuelta cuando este limiter se evalúa).
        RateLimiter::for('external-api', function (Request $request) {
            $apiKey = $request->attributes->get('api_key');

            return Limit::perMinute(60)->by($apiKey?->id ?? $request->ip());
        });
    }
}
