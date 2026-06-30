<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulator_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description');
            $table->enum('category', ['hvac', 'lighting', 'refrigerant', 'motor']);
            $table->unsignedTinyInteger('scope'); // 1, 2, or 3
            // Formula inputs (nullable — depends on category)
            $table->decimal('reduction_kwh_year', 10, 2)->nullable(); // energy scenarios
            $table->decimal('emission_factor_kgco2e_kwh', 8, 6)->nullable(); // kg CO2e per kWh
            $table->decimal('tariff_cop_kwh', 10, 2)->nullable();            // COP per kWh
            $table->decimal('reduction_kg_year', 10, 3)->nullable();         // refrigerant scenarios
            $table->unsignedSmallInteger('gwp')->nullable();                 // GWP of refrigerant
            // Pre-calculated annual figures (derived from formula inputs above)
            $table->decimal('annual_co2e_tco2e', 10, 4);
            $table->unsignedBigInteger('annual_savings_cop')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulator_scenarios');
    }
};
