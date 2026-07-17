<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carbon_emissions', function (Blueprint $table) {
            // Distingue origen manual vs IoT — evita que IoTCarbonIngestionService
            // sobreescriba en silencio una emisión cargada a mano para el mismo
            // [period_id, emission_factor_id].
            $table->string('source')->default('manual')->after('emission_factor_id');
        });
    }

    public function down(): void
    {
        Schema::table('carbon_emissions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
