<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Company;
use App\Models\Period;
use App\Models\EmissionFactor;
use App\Models\EmissionCategory;
use App\Models\Scope;
use App\Models\IotDevice;
use App\Models\TelemetryReading;
use App\Models\CarbonEmission;
use App\Services\IoTCarbonIngestionService;

class IoTCarbonIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private IoTCarbonIngestionService $service;
    private Company $company;
    private Period $period;
    private EmissionFactor $factor;
    private IotDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(IoTCarbonIngestionService::class);

        $scope          = Scope::firstOrCreate(['name' => 'Alcance 2'], ['description' => 'Scope 2']);
        $category       = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $this->factor   = EmissionFactor::factory()->create([
            'emission_category_id' => $category->id,
            'factor_total_co2e'    => 0.000126, // tCO2e per kWh (Colombia grid)
        ]);

        $this->company = Company::factory()->create();
        $this->period  = Period::factory()->create([
            'company_id' => $this->company->id,
            'year'       => now()->year,
            'status'     => 'open',
        ]);

        $this->device = IotDevice::create([
            'thingsboard_id'     => 'test-device-001',
            'name'               => 'Medidor Test',
            'type'               => 'energy',
            'unit'               => 'kWh',
            'company_id'         => $this->company->id,
            'emission_factor_id' => $this->factor->id,
        ]);
    }

    private function makeReading(float $value, ?string $timestamp = null): TelemetryReading
    {
        return TelemetryReading::create([
            'device_id'   => $this->device->id,
            'metric_name' => 'electricity_kwh',
            'value'       => $value,
            'timestamp'   => $timestamp ?? now()->toDateTimeString(),
        ]);
    }

    // ─── happy path ──────────────────────────────────────────────────────────

    public function test_ingest_creates_carbon_emission_for_active_period()
    {
        $reading  = $this->makeReading(100.0);
        $emission = $this->service->ingestReading($reading);

        $this->assertNotNull($emission);
        $this->assertEquals($this->period->id, $emission->period_id);
        $this->assertEquals($this->factor->id, $emission->emission_factor_id);
        $this->assertEqualsWithDelta(100.0, $emission->quantity, 0.01);
        $this->assertEqualsWithDelta(0.0126, $emission->calculated_co2e, 0.0001);
        $this->assertStringContainsString('IoT', $emission->notes);
    }

    public function test_ingest_is_idempotent_across_multiple_cron_runs()
    {
        // Simulate 3 cron runs each with one reading
        $r1 = $this->makeReading(50.0);
        $r2 = $this->makeReading(30.0);
        $r3 = $this->makeReading(20.0);

        $this->service->ingestReading($r1);
        $this->service->ingestReading($r2);
        $emission = $this->service->ingestReading($r3);

        // Only ONE CarbonEmission should exist (upsert)
        $this->assertEquals(1, CarbonEmission::count());
        // Total should be sum of all 3 readings, not last-only
        $this->assertEqualsWithDelta(100.0, $emission->quantity, 0.01);
    }

    // ─── skip conditions ─────────────────────────────────────────────────────

    public function test_ingest_returns_null_when_device_has_no_company()
    {
        $unconfigured = IotDevice::create([
            'thingsboard_id' => 'no-company',
            'name'           => 'Sin empresa',
            'type'           => 'energy',
            'unit'           => 'kWh',
        ]);
        $reading = TelemetryReading::create([
            'device_id'   => $unconfigured->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 50.0,
            'timestamp'   => now()->toDateTimeString(),
        ]);

        $result = $this->service->ingestReading($reading);

        $this->assertNull($result);
        $this->assertEquals(0, CarbonEmission::count());
    }

    public function test_ingest_returns_null_when_device_has_no_emission_factor()
    {
        $unconfigured = IotDevice::create([
            'thingsboard_id' => 'no-factor',
            'name'           => 'Sin factor',
            'type'           => 'energy',
            'unit'           => 'kWh',
            'company_id'     => $this->company->id,
        ]);
        $reading = TelemetryReading::create([
            'device_id'   => $unconfigured->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 50.0,
            'timestamp'   => now()->toDateTimeString(),
        ]);

        $result = $this->service->ingestReading($reading);

        $this->assertNull($result);
        $this->assertEquals(0, CarbonEmission::count());
    }

    public function test_ingest_returns_null_when_no_active_period()
    {
        $this->period->update(['status' => 'closed']);

        $reading = $this->makeReading(100.0);
        $result  = $this->service->ingestReading($reading);

        $this->assertNull($result);
        $this->assertEquals(0, CarbonEmission::count());
    }

    public function test_ingest_excludes_readings_outside_period_year()
    {
        // Reading from last year — should not be counted in current-year total
        $oldReading = $this->makeReading(9999.0, (now()->year - 1) . '-06-15 12:00:00');
        $newReading = $this->makeReading(100.0);

        $this->service->ingestReading($oldReading);
        $emission = $this->service->ingestReading($newReading);

        // Only the current-year reading should be included
        $this->assertEqualsWithDelta(100.0, $emission->quantity, 0.01);
    }

    // ─── origen manual vs IoT ────────────────────────────────────────────────

    public function test_ingest_marks_created_emission_with_source_iot()
    {
        $reading  = $this->makeReading(100.0);
        $emission = $this->service->ingestReading($reading);

        $this->assertEquals('iot', $emission->source);
    }

    public function test_ingest_does_not_overwrite_an_existing_manual_emission()
    {
        $manual = CarbonEmission::create([
            'period_id'          => $this->period->id,
            'emission_factor_id' => $this->factor->id,
            'source'             => 'manual',
            'quantity'           => 999.0,
            'calculated_co2e'    => 0.125874,
            'notes'              => 'Cargado a mano por el usuario',
        ]);

        $reading = $this->makeReading(100.0);
        $result  = $this->service->ingestReading($reading);

        $this->assertNull($result);
        $this->assertEquals(1, CarbonEmission::count());
        $manual->refresh();
        $this->assertEqualsWithDelta(999.0, $manual->quantity, 0.01);
        $this->assertEquals('manual', $manual->source);
    }

    // ─── múltiples dispositivos sobre el mismo factor ─────────────────────────

    public function test_ingest_combines_totals_from_multiple_devices_sharing_the_same_factor()
    {
        $device2 = IotDevice::create([
            'thingsboard_id'     => 'test-device-002',
            'name'               => 'Segundo Medidor',
            'type'               => 'energy',
            'unit'               => 'kWh',
            'company_id'         => $this->company->id,
            'emission_factor_id' => $this->factor->id, // mismo factor que $this->device
        ]);

        $readingDevice1 = $this->makeReading(100.0);
        $readingDevice2 = TelemetryReading::create([
            'device_id'   => $device2->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 40.0,
            'timestamp'   => now()->toDateTimeString(),
        ]);

        // Procesar primero el dispositivo 1, luego el 2 — como haría el cron
        // corriendo ambos dispositivos en la misma corrida.
        $this->service->ingestReading($readingDevice1);
        $emission = $this->service->ingestReading($readingDevice2);

        // Debe seguir habiendo UNA sola fila para [period, factor] — pero con
        // el total COMBINADO de ambos dispositivos, no solo el del último en
        // procesarse.
        $this->assertEquals(1, CarbonEmission::count());
        $this->assertEqualsWithDelta(140.0, $emission->quantity, 0.01);
    }

    public function test_ingest_from_one_device_does_not_discard_another_devices_prior_contribution()
    {
        $device2 = IotDevice::create([
            'thingsboard_id'     => 'test-device-003',
            'name'               => 'Tercer Medidor',
            'type'               => 'energy',
            'unit'               => 'kWh',
            'company_id'         => $this->company->id,
            'emission_factor_id' => $this->factor->id,
        ]);

        $this->service->ingestReading($this->makeReading(100.0));

        $readingDevice2 = TelemetryReading::create([
            'device_id'   => $device2->id,
            'metric_name' => 'electricity_kwh',
            'value'       => 25.0,
            'timestamp'   => now()->toDateTimeString(),
        ]);
        $emission = $this->service->ingestReading($readingDevice2);

        // Si device 2 sobreescribiera con solo su propio total, quantity
        // sería 25.0 — la contribución de device 1 se habría perdido.
        $this->assertEqualsWithDelta(125.0, $emission->quantity, 0.01);
    }
}
