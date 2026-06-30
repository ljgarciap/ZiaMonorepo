<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\IotDevice;
use App\Models\TelemetryReading;
use App\Services\BaseloadDeviationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BaseloadDeviationServiceTest extends TestCase
{
    use RefreshDatabase;

    private BaseloadDeviationService $service;
    private IotDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BaseloadDeviationService();

        $this->device = IotDevice::create([
            'thingsboard_id'     => 'sensor_test',
            'name'               => 'Test Energy Sensor',
            'type'               => 'energy',
            'location'           => 'Zone A',
            'unit'               => 'kWh',
            'baseline_kwh'       => 50.0,
            'office_hours_start' => '08:00:00',
            'office_hours_end'   => '18:00:00',
        ]);
    }

    public function test_returns_null_when_no_baseline_configured()
    {
        $this->device->baseline_kwh = null;

        $reading = TelemetryReading::create([
            'device_id'   => $this->device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 80.0,
            'timestamp'   => now(),
        ]);

        $result = $this->service->analyze($this->device, $reading);

        $this->assertNull($result);
    }

    public function test_returns_null_for_non_energy_device()
    {
        $waterDevice = IotDevice::create([
            'thingsboard_id' => 'sensor_water',
            'name'           => 'Water Sensor',
            'type'           => 'water',
            'location'       => 'Plant',
            'unit'           => 'm3',
            'baseline_kwh'   => 10.0,
        ]);

        $reading = TelemetryReading::create([
            'device_id'   => $waterDevice->id,
            'metric_name' => 'water_m3',
            'value'       => 20.0,
            'timestamp'   => now(),
        ]);

        $result = $this->service->analyze($waterDevice, $reading);

        $this->assertNull($result);
    }

    public function test_positive_deviation_above_baseline()
    {
        $reading = TelemetryReading::create([
            'device_id'   => $this->device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 75.0,
            'timestamp'   => Carbon::create(2026, 6, 29, 10, 0, 0), // working hours
        ]);

        $result = $this->service->analyze($this->device, $reading);

        $this->assertNotNull($result);
        $this->assertEquals(50.0, $result['baseline_kwh']);
        $this->assertEquals(50.0, $result['deviation_pct']); // (75-50)/50*100
        $this->assertEquals(25.0, $result['excess_kwh']);    // 75-50
        $this->assertFalse($result['is_off_hours']);
    }

    public function test_negative_deviation_below_baseline()
    {
        $reading = TelemetryReading::create([
            'device_id'   => $this->device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 30.0,
            'timestamp'   => Carbon::create(2026, 6, 29, 10, 0, 0),
        ]);

        $result = $this->service->analyze($this->device, $reading);

        $this->assertNotNull($result);
        $this->assertEquals(-40.0, $result['deviation_pct']); // (30-50)/50*100
        $this->assertEquals(0.0, $result['excess_kwh']);       // no excess when below baseline
    }

    public function test_off_hours_detected_for_weekend()
    {
        // Saturday at 10:00 AM — weekend = off hours
        $reading = TelemetryReading::create([
            'device_id'   => $this->device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 60.0,
            'timestamp'   => Carbon::create(2026, 6, 27, 10, 0, 0), // Saturday
        ]);

        $result = $this->service->analyze($this->device, $reading);

        $this->assertTrue($result['is_off_hours']);
    }

    public function test_off_hours_detected_after_end_time()
    {
        // Weekday at 20:00 — after office_hours_end (18:00)
        $reading = TelemetryReading::create([
            'device_id'   => $this->device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 55.0,
            'timestamp'   => Carbon::create(2026, 6, 30, 20, 0, 0), // Monday 8 PM
        ]);

        $result = $this->service->analyze($this->device, $reading);

        $this->assertTrue($result['is_off_hours']);
    }

    public function test_working_hours_not_flagged_as_off_hours()
    {
        // Wednesday at 14:00 — inside office hours
        $reading = TelemetryReading::create([
            'device_id'   => $this->device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 55.0,
            'timestamp'   => Carbon::create(2026, 6, 3, 14, 0, 0),
        ]);

        $result = $this->service->analyze($this->device, $reading);

        $this->assertFalse($result['is_off_hours']);
    }
}
