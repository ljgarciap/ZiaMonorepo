<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // M7 — Electricity emission factors versioned by year and region.
    // Allows inventories for past years to use the correct FECOC value instead of a single
    // hardcoded factor. Required for historical comparisons and audit compliance.
    public function up(): void
    {
        Schema::create('electricity_factors', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('region_code', 10)->default('CO');
            $table->decimal('value_kgco2e', 10, 6);
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['year', 'region_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electricity_factors');
    }
};
