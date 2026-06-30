<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->decimal('baseline_kwh', 10, 3)->nullable()->after('unit');
            $table->time('office_hours_start')->nullable()->default('08:00:00')->after('baseline_kwh');
            $table->time('office_hours_end')->nullable()->default('18:00:00')->after('office_hours_start');
        });

        // scopes.number for comparison grouping (1 = Alcance 1, 2 = Alcance 2, 3 = Alcance 3)
        Schema::table('scopes', function (Blueprint $table) {
            $table->unsignedTinyInteger('number')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->dropColumn(['baseline_kwh', 'office_hours_start', 'office_hours_end']);
        });

        Schema::table('scopes', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
