<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Company;
use App\Models\IotDevice;
use App\Models\TelemetryReading;
use App\Models\TelemetryAlert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TelemetryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private IotDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create(['role' => 'user']);
        $this->company = Company::factory()->create();
        $this->user->companies()->attach($this->company->id, ['role' => 'user', 'is_active' => true]);

        $this->device = IotDevice::create([
            'thingsboard_id' => 'sensor_elec_01',
            'name'           => 'Medidor Eléctrico P1',
            'type'           => 'energy',
            'location'       => 'Piso 1',
            'unit'           => 'kWh',
            'company_id'     => $this->company->id,
        ]);

        $this->actingAs($this->user, 'api');
    }

    // ─── live endpoint ────────────────────────────────────────────────────────

    public function test_live_returns_latest_reading_per_device()
    {
        $ts1 = Carbon::create(2026, 6, 29, 10, 0, 0);
        $ts2 = Carbon::create(2026, 6, 29, 10, 5, 0);

        TelemetryReading::create(['device_id' => $this->device->id, 'metric_name' => 'electricity_kwh', 'value' => 50.0, 'timestamp' => $ts1]);
        TelemetryReading::create(['device_id' => $this->device->id, 'metric_name' => 'electricity_kwh', 'value' => 65.0, 'timestamp' => $ts2]);

        $response = $this->getJson(
            '/api/telemetry/live',
            ['X-Company-ID' => (string) $this->company->id]
        );

        $response->assertOk()
                 ->assertJsonStructure(['readings', 'alerts']);

        $readings = $response->json('readings');
        $this->assertCount(1, $readings);
        $this->assertEquals(65.0, $readings[0]['value']);
        $this->assertEquals('Medidor Eléctrico P1', $readings[0]['device_name']);
    }

    public function test_live_includes_unresolved_alerts()
    {
        TelemetryReading::create([
            'device_id'   => $this->device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 30.0,
            'timestamp'   => now(),
        ]);

        TelemetryAlert::create([
            'device_id'       => $this->device->id,
            'alert_type'      => 'off_hours_excess',
            'severity'        => 'warning',
            'message'         => 'Consumo elevado',
            'threshold_value' => 25.0,
            'actual_value'    => 30.0,
            'detected_at'     => now(),
            'resolved'        => false,
        ]);

        TelemetryAlert::create([
            'device_id'       => $this->device->id,
            'alert_type'      => 'off_hours_excess',
            'severity'        => 'warning',
            'message'         => 'Alerta resuelta',
            'threshold_value' => 25.0,
            'actual_value'    => 28.0,
            'detected_at'     => now()->subHour(),
            'resolved'        => true,
        ]);

        $response = $this->getJson(
            '/api/telemetry/live',
            ['X-Company-ID' => (string) $this->company->id]
        );

        $response->assertOk();
        // Only the unresolved alert should appear
        $this->assertCount(1, $response->json('alerts'));
        $this->assertFalse($response->json('alerts.0.resolved'));
    }

    public function test_live_excludes_other_company_devices()
    {
        $otherCompany = Company::factory()->create();
        $otherDevice  = IotDevice::create([
            'thingsboard_id' => 'other_sensor',
            'name'           => 'Other Sensor',
            'type'           => 'energy',
            'location'       => 'Otro Edificio',
            'unit'           => 'kWh',
            'company_id'     => $otherCompany->id,
        ]);

        TelemetryReading::create(['device_id' => $otherDevice->id, 'metric_name' => 'electricity_kwh', 'value' => 99.0, 'timestamp' => now()]);
        TelemetryReading::create(['device_id' => $this->device->id,  'metric_name' => 'electricity_kwh', 'value' => 10.0, 'timestamp' => now()]);

        $response = $this->getJson(
            '/api/telemetry/live',
            ['X-Company-ID' => (string) $this->company->id]
        );

        $response->assertOk();
        $values = collect($response->json('readings'))->pluck('value')->map(fn($v) => (float) $v);
        $this->assertContainsEquals(10.0, $values->toArray());
        $this->assertNotContainsEquals(99.0, $values->toArray());
    }

    // ─── history endpoint ─────────────────────────────────────────────────────

    public function test_history_returns_paginated_readings()
    {
        for ($i = 0; $i < 5; $i++) {
            TelemetryReading::create([
                'device_id'   => $this->device->id,
                'metric_name' => 'electricity_kwh',
                'value'       => 40.0 + $i,
                'timestamp'   => now()->subMinutes($i * 5),
            ]);
        }

        $response = $this->getJson(
            '/api/telemetry/history?per_page=3',
            ['X-Company-ID' => (string) $this->company->id]
        );

        $response->assertOk()
                 ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);

        $this->assertEquals(5, $response->json('total'));
        $this->assertCount(3, $response->json('data'));
    }

    public function test_history_filters_by_device_id()
    {
        $secondDevice = IotDevice::create([
            'thingsboard_id' => 'sensor_water_01',
            'name'           => 'Medidor Agua',
            'type'           => 'water',
            'location'       => 'Planta',
            'unit'           => 'm3',
            'company_id'     => $this->company->id,
        ]);

        TelemetryReading::create(['device_id' => $this->device->id,  'metric_name' => 'electricity_kwh', 'value' => 55.0, 'timestamp' => now()]);
        TelemetryReading::create(['device_id' => $secondDevice->id,  'metric_name' => 'water_m3',        'value' => 0.5,  'timestamp' => now()]);

        $response = $this->getJson(
            "/api/telemetry/history?device_id={$this->device->id}",
            ['X-Company-ID' => (string) $this->company->id]
        );

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('electricity_kwh', $response->json('data.0.metric_name'));
    }

    public function test_live_requires_authentication()
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/telemetry/live')->assertStatus(401);
    }
}
