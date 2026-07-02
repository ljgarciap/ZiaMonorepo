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
        Schema::table('activity_logs', function (Blueprint $table) {
            // Marca cuando un superadmin actúa en un contexto operativo (p.ej. portal
            // Admin restringido) en vez de su rol natural — acceso excepcional documentado.
            $table->boolean('is_exceptional')->default(false)->after('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('is_exceptional');
        });
    }
};
