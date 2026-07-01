<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('sector')->nullable(); // sector/industry targeting
            $table->string('status')->default('draft'); // draft, published, archived
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('questionnaire_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('questionnaire_templates')->onDelete('cascade');
            $table->string('question_text');
            $table->string('question_type'); // text, number, select, multiselect, boolean, file
            $table->json('options')->nullable(); // for select/multiselect
            $table->string('unit')->nullable(); // for number questions
            $table->string('scope_hint')->nullable(); // scope_1, scope_2, scope_3
            $table->string('category_hint')->nullable(); // e.g. 'Combustion estacionaria'
            $table->boolean('required')->default(true);
            $table->text('help_text')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_questions');
        Schema::dropIfExists('questionnaire_templates');
    }
};
