<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_devices', function (Blueprint $table) {
            $table->id();
            $table->string('thingsboard_id')->unique()->nullable();
            $table->string('name');
            $table->string('type'); // e.g. 'energy', 'water'
            $table->string('location')->nullable(); // e.g. 'ECONOVA Piso 1'
            $table->string('unit')->nullable(); // e.g. 'kWh', 'm3'
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('telemetry_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('iot_devices')->onDelete('cascade');
            $table->string('metric_name'); // e.g. 'electricity_kwh', 'water_m3'
            $table->double('value');
            $table->timestamp('timestamp');
            $table->timestamps();

            // Timeseries compound index for fast retrieval
            $table->index(['device_id', 'timestamp']);
            $table->index('timestamp');
        });

        Schema::create('telemetry_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('iot_devices')->onDelete('cascade');
            $table->string('alert_type'); // e.g. 'off_hours_excess'
            $table->string('severity'); // e.g. 'warning', 'critical'
            $table->text('message');
            $table->double('threshold_value');
            $table->double('actual_value');
            $table->timestamp('detected_at');
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_alerts');
        Schema::dropIfExists('telemetry_readings');
        Schema::dropIfExists('iot_devices');
    }
};
