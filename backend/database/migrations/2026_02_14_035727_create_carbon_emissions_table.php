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
        Schema::dropIfExists('carbon_emissions'); // Drop if exists to recreate clean
        Schema::create('carbon_emissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->onDelete('cascade');
            $table->foreignId('emission_factor_id')->constrained()->onDelete('restrict');
            
            $table->decimal('quantity', 12, 4); // User input
            
            // Detailed results
            $table->decimal('emissions_co2', 15, 8)->default(0);
            $table->decimal('emissions_ch4', 15, 8)->default(0);
            $table->decimal('emissions_n2o', 15, 8)->default(0);
            $table->decimal('emissions_nf3', 15, 8)->default(0);
            $table->decimal('emissions_sf6', 15, 8)->default(0);
            $table->decimal('calculated_co2e', 15, 8); // Total Result
            
            $table->decimal('uncertainty_result', 12, 6)->nullable(); // +/- %
            $table->decimal('activity_data_total', 15, 8)->nullable(); // for aggregation
            $table->decimal('activity_data_stdev', 15, 8)->nullable(); // for uncertainty
            
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carbon_emissions');
    }
};
