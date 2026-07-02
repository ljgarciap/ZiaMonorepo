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
        Schema::table('companies', function (Blueprint $table) {
            // Superadmin: "Aprobar estructuras metodológicas alineadas con ISO 14064-1
            // y GHG Protocol" (spec 1.2.3)
            $table->boolean('is_methodology_approved')->default(false);
            $table->timestamp('methodology_approved_at')->nullable();
            $table->foreignId('methodology_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['methodology_approved_by']);
            $table->dropColumn(['is_methodology_approved', 'methodology_approved_at', 'methodology_approved_by']);
        });
    }
};
