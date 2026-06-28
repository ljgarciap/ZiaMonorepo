<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_questionnaire_rules', function (Blueprint $table) {
            $table->id();
            $table->string('sector_code', 50)->index();
            $table->foreignId('emission_factor_id')->constrained()->onDelete('cascade');
            $table->string('questionnaire_label')->comment('Pregunta visible al usuario');
            $table->string('variable_name', 50)->comment('Nombre de variable para mathjs');
            $table->string('input_unit_hint', 20)->nullable()->comment('Hint de unidad: kWh, m3, Gal, kg');
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->string('help_text')->nullable();
            $table->timestamps();

            $table->unique(['sector_code', 'emission_factor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_questionnaire_rules');
    }
};
