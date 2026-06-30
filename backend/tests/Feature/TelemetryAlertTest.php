<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\IotDevice;
use App\Models\TelemetryReading;
use App\Models\TelemetryAlert;
use App\Services\TelemetryAlertService;
use App\Services\BaseloadDeviationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class TelemetryAlertTest extends TestCase
{
    use RefreshDatabase;

    protected $alertService;
    protected $energyDevice;
    protected $waterDevice;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->alertService = new TelemetryAlertService(new BaseloadDeviationService());

        // Setup test devices
        $this->energyDevice = IotDevice::create([
            'thingsboard_id' => 'test_energy',
            'name' => 'Test Energy Sensor',
            'type' => 'energy',
            'location' => 'Zone A',
            'unit' => 'kWh'
        ]);

        $this->waterDevice = IotDevice::create([
            'thingsboard_id' => 'test_water',
            'name' => 'Test Water Sensor',
            'type' => 'water',
            'location' => 'Zone B',
            'unit' => 'm3'
        ]);
    }

    /**
     * Test that working hours reading does not trigger alerts even if high.
     */
    public function test_working_hours_high_reading_triggers_no_alerts()
    {
        // Wednesday 10:00 AM
        $ts = Carbon::create(2026, 6, 3, 10, 0, 0);

        $reading = TelemetryReading::create([
            'device_id' => $this->energyDevice->id,
            'metric_name' => 'electricity_kwh',
            'value' => 85.0, // High consumption but within working hours
            'timestamp' => $ts
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNull($alert);
        $this->assertDatabaseCount('telemetry_alerts', 0);
    }

    /**
     * Test that off-hours reading below maximum threshold triggers no alerts.
     */
    public function test_off_hours_normal_reading_triggers_no_alerts()
    {
        // Sunday 3:00 AM
        $ts = Carbon::create(2026, 6, 7, 3, 0, 0);

        $reading = TelemetryReading::create([
            'device_id' => $this->energyDevice->id,
            'metric_name' => 'electricity_kwh',
            'value' => 12.0, // Normal night draw (threshold is 25.0)
            'timestamp' => $ts
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNull($alert);
        $this->assertDatabaseCount('telemetry_alerts', 0);
    }

    /**
     * Test that off-hours high reading triggers telemetry alert.
     */
    public function test_off_hours_excessive_reading_triggers_warning_alert()
    {
        // Sunday 3:00 AM
        $ts = Carbon::create(2026, 6, 7, 3, 0, 0);

        $reading = TelemetryReading::create([
            'device_id' => $this->energyDevice->id,
            'metric_name' => 'electricity_kwh',
            'value' => 38.0, // Exceeds threshold of 25.0
            'timestamp' => $ts
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNotNull($alert);
        $this->assertEquals('off_hours_excess', $alert->alert_type);
        $this->assertEquals('warning', $alert->severity);
        $this->assertEquals(38.0, $alert->actual_value);
        
        $this->assertDatabaseCount('telemetry_alerts', 1);
        $this->assertDatabaseHas('telemetry_alerts', [
            'device_id' => $this->energyDevice->id,
            'severity' => 'warning',
            'actual_value' => 38.0
        ]);
    }

    /**
     * Test that off-hours extreme reading triggers critical alert.
     */
    public function test_off_hours_extreme_reading_triggers_critical_alert()
    {
        // Sunday 3:00 AM
        $ts = Carbon::create(2026, 6, 7, 3, 0, 0);

        $reading = TelemetryReading::create([
            'device_id' => $this->waterDevice->id,
            'metric_name' => 'water_m3',
            'value' => 1.5, // Extreme night flow (leakage threshold 0.3, critical threshold 1.0)
            'timestamp' => $ts
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNotNull($alert);
        $this->assertEquals('critical', $alert->severity);
        $this->assertStringContainsString('Posible fuga activa', $alert->message);
        
        $this->assertDatabaseCount('telemetry_alerts', 1);
    }

    /**
     * Water flow of 0.2 m3 on a Sunday night is below the 0.3 threshold — no alert.
     */
    public function test_water_device_normal_off_hours_triggers_no_alert()
    {
        // Sunday 2:00 AM
        $ts = Carbon::create(2026, 6, 7, 2, 0, 0);

        $reading = TelemetryReading::create([
            'device_id'   => $this->waterDevice->id,
            'metric_name' => 'water_m3',
            'value'       => 0.2, // Below 0.3 m3 threshold
            'timestamp'   => $ts,
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNull($alert);
        $this->assertDatabaseCount('telemetry_alerts', 0);
    }

    /**
     * Energy reading > 40 kWh off-hours triggers a critical severity alert.
     */
    public function test_energy_device_critical_extreme_triggers_critical_alert()
    {
        // Saturday 1:00 AM
        $ts = Carbon::create(2026, 6, 6, 1, 0, 0);

        $reading = TelemetryReading::create([
            'device_id'   => $this->energyDevice->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 95.0, // Extreme — well above 40 kWh critical threshold
            'timestamp'   => $ts,
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNotNull($alert);
        $this->assertEquals('critical', $alert->severity);
        $this->assertEquals(95.0, $alert->actual_value);
        $this->assertDatabaseCount('telemetry_alerts', 1);
    }

    // ─── waste device (Sprint 9) ──────────────────────────────────────────────

    public function test_waste_device_off_hours_excessive_reading_triggers_warning()
    {
        $device = IotDevice::create([
            'thingsboard_id' => 'bascula_01',
            'name'           => 'Báscula Residuos P1',
            'type'           => 'waste',
            'location'       => 'ECONOVA Piso 1',
            'unit'           => 'kg',
        ]);

        // Sunday 2:00 AM — off hours
        $ts = Carbon::create(2026, 6, 7, 2, 0, 0);

        $reading = TelemetryReading::create([
            'device_id'   => $device->id,
            'metric_name' => 'waste_kg',
            'value'       => 25.0,  // above 10 kg threshold
            'timestamp'   => $ts,
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNotNull($alert);
        $this->assertEquals('warning', $alert->severity);
        $this->assertEquals(10.0, $alert->threshold_value);
        $this->assertEquals(25.0, $alert->actual_value);
    }

    public function test_waste_device_off_hours_critical_triggers_critical()
    {
        $device = IotDevice::create([
            'thingsboard_id' => 'bascula_02',
            'name'           => 'Báscula Residuos P2',
            'type'           => 'waste',
            'location'       => 'ECONOVA Piso 2',
            'unit'           => 'kg',
        ]);

        $ts = Carbon::create(2026, 6, 7, 3, 0, 0);

        $reading = TelemetryReading::create([
            'device_id'   => $device->id,
            'metric_name' => 'waste_kg',
            'value'       => 75.0,  // above 50 kg → critical
            'timestamp'   => $ts,
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNotNull($alert);
        $this->assertEquals('critical', $alert->severity);
    }

    // ─── T2: baseload deviation alerts ───────────────────────────────────────

    public function test_baseload_excess_triggers_warning_during_working_hours(): void
    {
        $device = IotDevice::create([
            'thingsboard_id'    => 'energy_baseline_01',
            'name'              => 'Medidor con Baseline',
            'type'              => 'energy',
            'location'          => 'Piso 3',
            'unit'              => 'kWh',
            'baseline_kwh'      => 50.0,   // 50 kWh baseline
            'office_hours_start'=> '08:00:00',
            'office_hours_end'  => '18:00:00',
        ]);

        // Tuesday 10:00 AM — working hours, 65 kWh = 30% above baseline
        $ts = Carbon::create(2026, 6, 9, 10, 0, 0);

        $reading = TelemetryReading::create([
            'device_id'   => $device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 65.0,
            'timestamp'   => $ts,
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNotNull($alert);
        $this->assertEquals('baseload_excess', $alert->alert_type);
        $this->assertEquals('warning', $alert->severity);
        $this->assertEquals(50.0, $alert->threshold_value);
        $this->assertEquals(65.0, $alert->actual_value);
        $this->assertStringContainsString('30%', $alert->message);
    }

    public function test_baseload_excess_no_alert_during_off_hours(): void
    {
        $device = IotDevice::create([
            'thingsboard_id'    => 'energy_baseline_02',
            'name'              => 'Medidor con Baseline 2',
            'type'              => 'energy',
            'location'          => 'Piso 3',
            'unit'              => 'kWh',
            'baseline_kwh'      => 10.0,   // small baseline so value can be 30% above but still < 25 kWh off-hours threshold
            'office_hours_start'=> '08:00:00',
            'office_hours_end'  => '18:00:00',
        ]);

        // Saturday 10:00 AM — off-hours (weekend).
        // value=13: 30% above baseline=10, but 13 < 25 off_hours_excess threshold → no off_hours alert.
        // BaseloadDeviationService.isOffHours() → weekend=true → is_off_hours=true → no baseload alert.
        $ts = Carbon::create(2026, 6, 6, 10, 0, 0);

        $reading = TelemetryReading::create([
            'device_id'   => $device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 13.0,
            'timestamp'   => $ts,
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNull($alert);
        $this->assertDatabaseCount('telemetry_alerts', 0);
    }

    public function test_no_baseload_alert_when_deviation_below_threshold(): void
    {
        $device = IotDevice::create([
            'thingsboard_id'    => 'energy_baseline_03',
            'name'              => 'Medidor con Baseline 3',
            'type'              => 'energy',
            'location'          => 'Piso 3',
            'unit'              => 'kWh',
            'baseline_kwh'      => 50.0,
            'office_hours_start'=> '08:00:00',
            'office_hours_end'  => '18:00:00',
        ]);

        // Tuesday 10:00 AM — working hours, 55 kWh = only 10% above baseline (threshold is 20%)
        $ts = Carbon::create(2026, 6, 9, 10, 0, 0);

        $reading = TelemetryReading::create([
            'device_id'   => $device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 55.0,
            'timestamp'   => $ts,
        ]);

        $alert = $this->alertService->checkReading($reading);

        $this->assertNull($alert);
        $this->assertDatabaseCount('telemetry_alerts', 0);
    }

    /**
     * A device with an unrecognized type must be handled gracefully — no exception, no alert.
     */
    public function test_unknown_device_type_handled_gracefully()
    {
        $solarDevice = IotDevice::create([
            'thingsboard_id' => 'solar_panel_01',
            'name'           => 'Solar Panel Sensor',
            'type'           => 'solar', // Not 'energy' or 'water'
            'location'       => 'Roof',
            'unit'           => 'kW',
        ]);

        // Saturday 3:00 AM
        $ts = Carbon::create(2026, 6, 6, 3, 0, 0);

        $reading = TelemetryReading::create([
            'device_id'   => $solarDevice->id,
            'metric_name' => 'solar_kw',
            'value'       => 500.0, // Very high — but unknown type should not trigger
            'timestamp'   => $ts,
        ]);

        // Must not throw any exception
        $alert = $this->alertService->checkReading($reading);

        $this->assertNull($alert);
        $this->assertDatabaseCount('telemetry_alerts', 0);
    }
}
