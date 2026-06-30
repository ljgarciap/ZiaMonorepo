<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 9-7 — persist monthly inputs for audit traceability
        Schema::table('carbon_emissions', function (Blueprint $table) {
            $table->json('monthly_data')->nullable()->after('notes');
        });

        // 9-8 — subsectors master table
        Schema::create('subsectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_sector_id')->constrained()->onDelete('cascade');
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 9-8 — allow questionnaire rules to be scoped by subsector
        Schema::table('sector_questionnaire_rules', function (Blueprint $table) {
            $table->string('subsector_code', 50)->nullable()->after('sector_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('carbon_emissions', function (Blueprint $table) {
            $table->dropColumn('monthly_data');
        });

        Schema::table('sector_questionnaire_rules', function (Blueprint $table) {
            $table->dropColumn('subsector_code');
        });

        Schema::dropIfExists('subsectors');
    }
};
