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
        Schema::table('carbon_emissions', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->after('user_id');
            $table->foreign('unit_id')->references('id')->on('operational_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carbon_emissions', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
