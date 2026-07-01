<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('nit');
            $table->string('contact_phone', 30)->nullable()->after('contact_email');
            $table->string('legal_rep')->nullable()->after('contact_phone');
            // 'address' already exists in some environments — skip
            $table->unsignedSmallInteger('base_year')->nullable()->after('address');
            $table->string('methodology', 50)->nullable()->after('base_year');
            $table->text('decarbonization_goal')->nullable()->after('methodology');
            $table->unsignedSmallInteger('decarbonization_year')->nullable()->after('decarbonization_goal');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'contact_email', 'contact_phone', 'legal_rep', 'address',
                'base_year', 'methodology', 'decarbonization_goal', 'decarbonization_year',
            ]);
        });
    }
};
