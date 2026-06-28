<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_sectors', function (Blueprint $table) {
            $table->string('code', 50)->unique()->nullable()->after('name');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('subsector_code', 50)->nullable()->after('company_sector_id');
            $table->jsonb('tags')->nullable()->after('subsector_code');
            $table->unsignedInteger('num_employees')->nullable()->after('tags');
            $table->unsignedInteger('floor_sqm')->nullable()->after('num_employees');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['subsector_code', 'tags', 'num_employees', 'floor_sqm']);
        });

        Schema::table('company_sectors', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
