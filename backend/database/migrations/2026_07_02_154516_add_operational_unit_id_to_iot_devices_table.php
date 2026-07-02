<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            // Técnico IoT: asociar el dispositivo a una unidad operativa/área específica (spec 1.1)
            $table->foreignId('operational_unit_id')->nullable()->constrained('operational_units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->dropForeign(['operational_unit_id']);
            $table->dropColumn('operational_unit_id');
        });
    }
};
