<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            // Último valor crudo leído del sensor (contador acumulado, ej. energy_active_import_wh).
            // Permite calcular el delta del intervalo sin re-derivarlo de telemetry_readings.
            $table->double('last_raw_value')->nullable()->after('unit');
            // Última vez que se sincronizó con éxito (usado para pedir el rango de eventos
            // pendientes en dispositivos que reportan por evento, ej. báscula de peso).
            $table->timestamp('last_synced_at')->nullable()->after('last_raw_value');
        });
    }

    public function down(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->dropColumn(['last_raw_value', 'last_synced_at']);
        });
    }
};
