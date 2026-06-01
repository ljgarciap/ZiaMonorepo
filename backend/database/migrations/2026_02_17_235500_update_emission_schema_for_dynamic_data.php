<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update Emission Categories first
        if (!Schema::hasColumn('emission_categories', 'scope_id')) {
             Schema::table('emission_categories', function (Blueprint $table) {
                $table->foreignId('scope_id')->nullable()->after('name')->constrained('scopes')->onDelete('cascade');
            });
        }

        // Data Migration for Scopes
        // Assuming current 'scope' column is enum '1','2','3' and maps directly to scopes table IDs 1, 2, 3
        if (Schema::hasColumn('emission_categories', 'scope')) {
             DB::statement("UPDATE emission_categories SET scope_id = CAST(scope AS UNSIGNED) WHERE scope IS NOT NULL");
             
             // Make it required after migration
            Schema::table('emission_categories', function (Blueprint $table) {
                $table->foreignId('scope_id')->nullable(false)->change();
                $table->dropColumn('scope');
            });
        }


        // 2. Update Emission Factors
        if (!Schema::hasColumn('emission_factors', 'measurement_unit_id')) {
            Schema::table('emission_factors', function (Blueprint $table) {
                $table->foreignId('measurement_unit_id')->nullable()->after('name')->constrained('measurement_units')->onDelete('set null');
            });
        }

        // Data Migration for Units
        // We need to ensure all current string units exist in the measurement_units table
        if (Schema::hasColumn('emission_factors', 'unit')) {
            $existingUnits = DB::table('emission_factors')->select('unit')->distinct()->whereNotNull('unit')->get();

            foreach ($existingUnits as $row) {
                $unitName = trim($row->unit);
                if (empty($unitName)) continue;

                // Check if exists, if not create
                $unitId = DB::table('measurement_units')->where('symbol', $unitName)->orWhere('name', $unitName)->value('id');

                if (!$unitId) {
                    $unitId = DB::table('measurement_units')->insertGetId([
                        'name' => $unitName, // Use the string as name
                        'symbol' => $unitName, // Use the string as symbol too for now
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Update factors
                DB::table('emission_factors')
                    ->where('unit', $row->unit)
                    ->update(['measurement_unit_id' => $unitId]);
            }

            // Drop old unit column? 
            // User wants validation, so yes.
            Schema::table('emission_factors', function (Blueprint $table) {
                $table->dropColumn('unit');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert Categories
        Schema::table('emission_categories', function (Blueprint $table) {
            $table->enum('scope', ['1', '2', '3'])->nullable();
        });

        DB::statement("UPDATE emission_categories SET scope = CAST(scope_id AS CHAR) WHERE scope_id IS NOT NULL");

        Schema::table('emission_categories', function (Blueprint $table) {
             $table->dropForeign(['scope_id']);
             $table->dropColumn('scope_id');
        });

        // Revert Factors
        Schema::table('emission_factors', function (Blueprint $table) {
            $table->string('unit')->nullable();
        });

        // Try to restore unit string from relationship
        // This is tricky in SQL directly without joins in update which vary by DB driver
        // For simplicity in down(), we might leave unit empty or try a join update if needed.
        // DB::statement("UPDATE emission_factors ef JOIN measurement_units mu ON ef.measurement_unit_id = mu.id SET ef.unit = mu.symbol");

        Schema::table('emission_factors', function (Blueprint $table) {
            $table->dropForeign(['measurement_unit_id']);
            $table->dropColumn('measurement_unit_id');
        });
    }
};
