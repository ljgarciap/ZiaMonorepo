<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La migración anterior (2026_07_17_120100) agregó `source` con
     * ->default('manual'), lo que backfillea 'manual' en TODAS las filas
     * existentes de carbon_emissions — incluidas las que
     * IoTCarbonIngestionService ya había creado antes de que existiera esta
     * columna. El guard nuevo de esa misma feature (no sobreescribir
     * source=manual) congelaría en silencio la ingesta IoT para esos
     * [period_id, emission_factor_id] en cualquier entorno que ya tuviera
     * datos. No se edita la migración anterior (ya corrida en algunos
     * entornos) — se corrige hacia adelante con esta.
     */
    public function up(): void
    {
        DB::table('carbon_emissions')
            ->where('notes', 'like', 'Auto-ingested from IoT:%')
            ->update(['source' => 'iot']);
    }

    public function down(): void
    {
        DB::table('carbon_emissions')
            ->where('notes', 'like', 'Auto-ingested from IoT:%')
            ->update(['source' => 'manual']);
    }
};
