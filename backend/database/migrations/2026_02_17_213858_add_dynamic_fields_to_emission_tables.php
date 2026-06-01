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
        Schema::table('emission_categories', function (Blueprint $table) {
            $table->foreignId('scope_id')->nullable()->constrained('scopes')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('emission_categories')->onDelete('cascade');
            // We'll drop the enum 'scope' later or ignore it for now to avoid data loss before migration
        });

        Schema::table('emission_factors', function (Blueprint $table) {
            $table->foreignId('measurement_unit_id')->nullable()->constrained('measurement_units')->onDelete('set null');
            // We'll drop 'unit' string later
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emission_categories', function (Blueprint $table) {
            $table->dropForeign(['scope_id']);
            $table->dropColumn('scope_id');
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });

        Schema::table('emission_factors', function (Blueprint $table) {
            $table->dropForeign(['measurement_unit_id']);
            $table->dropColumn('measurement_unit_id');
        });
    }
};
