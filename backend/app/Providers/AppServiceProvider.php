<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
    }
}
