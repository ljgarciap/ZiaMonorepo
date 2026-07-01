<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurement_units', function (Blueprint $table) {
            $table->boolean('is_standard')->default(false)->after('symbol');
            $table->boolean('is_active')->default(true)->after('is_standard');
        });
    }

    public function down(): void
    {
        Schema::table('measurement_units', function (Blueprint $table) {
            $table->dropColumn(['is_standard', 'is_active']);
        });
    }
};
