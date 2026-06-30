<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // M1 + M3 — emission_factors: removal and biogenic flags
        Schema::table('emission_factors', function (Blueprint $table) {
            $table->boolean('is_removal')->default(false)->after('source_reference');
            $table->boolean('is_biogenic')->default(false)->after('is_removal');
        });

        // M2 + Scope2 dual — carbon_emissions: stored carbon, avoided, biogenic, scope2 method
        Schema::table('carbon_emissions', function (Blueprint $table) {
            $table->decimal('biogenic_co2e', 15, 8)->default(0)->after('calculated_co2e');
            $table->decimal('carbon_stored', 15, 8)->nullable()->after('biogenic_co2e');
            $table->decimal('avoided_emissions', 15, 8)->nullable()->after('carbon_stored');
            $table->string('scope2_method')->nullable()->after('avoided_emissions');
        });

        // M4 — periods: base year flag
        Schema::table('periods', function (Blueprint $table) {
            $table->boolean('is_base_year')->default(false)->after('status');
        });

        // M5 — companies: consolidation approach (GHG Protocol boundary definition)
        Schema::table('companies', function (Blueprint $table) {
            $table->string('consolidation_approach')->nullable()->after('logo_url');
        });

        // M6 — emission_categories: Scope 3 category number (1-15 per GHG Protocol)
        Schema::table('emission_categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('scope3_category_number')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('emission_factors', function (Blueprint $table) {
            $table->dropColumn(['is_removal', 'is_biogenic']);
        });

        Schema::table('carbon_emissions', function (Blueprint $table) {
            $table->dropColumn(['biogenic_co2e', 'carbon_stored', 'avoided_emissions', 'scope2_method']);
        });

        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn('is_base_year');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('consolidation_approach');
        });

        Schema::table('emission_categories', function (Blueprint $table) {
            $table->dropColumn('scope3_category_number');
        });
    }
};
