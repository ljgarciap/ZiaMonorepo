<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->foreignId('company_id')
                  ->nullable()
                  ->after('unit')
                  ->constrained('companies')
                  ->nullOnDelete();

            $table->foreignId('emission_factor_id')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('emission_factors')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->dropForeign(['emission_factor_id']);
            $table->dropForeign(['company_id']);
            $table->dropColumn(['emission_factor_id', 'company_id']);
        });
    }
};
