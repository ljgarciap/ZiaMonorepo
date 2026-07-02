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
            // Técnico IoT: registro de pruebas de calibración (spec 1.1)
            $table->timestamp('last_calibrated_at')->nullable();
            $table->text('calibration_notes')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->dropForeign(['registered_by']);
            $table->dropColumn(['last_calibrated_at', 'calibration_notes', 'registered_by']);
        });
    }
};
