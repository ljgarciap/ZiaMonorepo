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
        Schema::create('calculation_formulas', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Combustion Station'
            $table->text('expression'); // e.g., '(activity_data * factor_values["co2"]) / 1000'
            $table->json('variables')->nullable(); // Metadata about expected variables
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculation_formulas');
    }
};
