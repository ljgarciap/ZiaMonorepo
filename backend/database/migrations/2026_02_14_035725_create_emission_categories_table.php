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
        Schema::create('emission_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Fuentes Móviles - Gasolina'
            $table->enum('scope', ['1', '2', '3']);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emission_categories');
    }
};
