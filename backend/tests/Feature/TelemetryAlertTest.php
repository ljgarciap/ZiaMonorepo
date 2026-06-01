<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\IotDevice;
use App\Models\TelemetryReading;
use App\Models\TelemetryAlert;
use App\Services\TelemetryAlertService;
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
        
        $this->alertService = new TelemetryAlertService();

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
}
