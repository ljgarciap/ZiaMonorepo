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
        Schema::create('company_emission_factor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('emission_factor_id')->constrained('emission_factors')->onDelete('cascade');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'emission_factor_id'], 'company_factor_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_emission_factor');
    }
};
