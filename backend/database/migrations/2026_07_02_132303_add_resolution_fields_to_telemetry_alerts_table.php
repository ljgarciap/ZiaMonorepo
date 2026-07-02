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
        Schema::table('telemetry_alerts', function (Blueprint $table) {
            // Técnico IoT: diagnóstico registrado al resolver una alerta (spec 1.1)
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemetry_alerts', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['resolution_note', 'resolved_by', 'resolved_at']);
        });
    }
};
