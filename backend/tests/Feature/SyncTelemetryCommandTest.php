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
use App\Services\ThingsBoardService;

class SyncTelemetryCommandTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Period $period;
    private EmissionFactor $factor;

    protected function setUp(): void
    {
        parent::setUp();

        $scope = Scope::firstOrCreate(['name' => 'Alcance 2'], ['description' => 'Scope 2']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $this->factor = EmissionFactor::factory()->create([
            'emission_category_id' => $category->id,
            'factor_total_co2e' => 0.000126,
        ]);

        $this->company = Company::factory()->create();
        $this->period = Period::factory()->create([
            'company_id' => $this->company->id,
            'year' => now()->year,
            'status' => 'open',
        ]);
    }

    private function makeDevice(array $overrides = []): IotDevice
    {
        return IotDevice::create(array_merge([
            'thingsboard_id' => 'device-under-test',
            'name' => 'Dispositivo de prueba',
            'type' => 'energy',
            'unit' => 'kWh',
            'company_id' => $this->company->id,
            'emission_factor_id' => $this->factor->id,
        ], $overrides));
    }

    // ─── energía: contador acumulado ───────────────────────────────────────

    public function test_energy_first_sync_discards_baseline_and_stores_zero_delta()
    {
        $device = $this->makeDevice(['last_raw_value' => null]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldReceive('getLatestTelemetry')
                ->with('device-under-test', 'energy_active_import_wh')
                ->andReturn(['value' => 5000.0, 'timestamp' => now()->toDateTimeString(), 'is_fallback' => false]);
        });

        $this->artisan('zia:sync-telemetry')->assertExitCode(0);

        $reading = TelemetryReading::where('device_id', $device->id)->first();
        $this->assertNotNull($reading);
        $this->assertEqualsWithDelta(0.0, $reading->value, 0.0001);
        $this->assertEquals(5000.0, $device->fresh()->last_raw_value);
    }

    public function test_energy_second_sync_computes_delta_in_kwh()
    {
        $device = $this->makeDevice(['last_raw_value' => 5000.0]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldReceive('getLatestTelemetry')
                ->with('device-under-test', 'energy_active_import_wh')
                ->andReturn(['value' => 6500.0, 'timestamp' => now()->toDateTimeString(), 'is_fallback' => false]);
        });

        $this->artisan('zia:sync-telemetry')->assertExitCode(0);

        $reading = TelemetryReading::where('device_id', $device->id)->first();
        $this->assertEqualsWithDelta(1.5, $reading->value, 0.0001); // (6500-5000) Wh = 1.5 kWh
        $this->assertEquals(6500.0, $device->fresh()->last_raw_value);
    }

    public function test_energy_sync_skips_cycle_when_telemetry_is_a_fallback_value()
    {
        $device = $this->makeDevice(['last_raw_value' => 5000.0]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldReceive('getLatestTelemetry')
                ->with('device-under-test', 'energy_active_import_wh')
                // Falla real de la API: ThingsBoardService cae a un valor
                // simulado y lo marca is_fallback — no debe tratarse como una
                // lectura real del contador acumulado.
                ->andReturn(['value' => 42.0, 'timestamp' => now()->toDateTimeString(), 'is_fallback' => true]);
        });

        $this->artisan('zia:sync-telemetry')->assertExitCode(0);

        $this->assertEquals(0, TelemetryReading::where('device_id', $device->id)->count());
        // La línea base NO se toca — si se hubiera persistido 42.0, el
        // próximo delta real (miles de Wh - 42) quedaría masivamente inflado.
        $this->assertEquals(5000.0, $device->fresh()->last_raw_value);
    }

    public function test_energy_sync_does_not_advance_last_raw_value_when_ingestion_fails()
    {
        $device = $this->makeDevice(['last_raw_value' => 5000.0]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldReceive('getLatestTelemetry')
                ->with('device-under-test', 'energy_active_import_wh')
                ->andReturn(['value' => 6500.0, 'timestamp' => now()->toDateTimeString(), 'is_fallback' => false]);
        });
        $this->mock(\App\Services\IoTCarbonIngestionService::class, function ($mock) {
            $mock->shouldReceive('ingestReading')->andThrow(new \RuntimeException('DB caída a mitad de la ingesta'));
        });

        // El comando no debe abortar por una excepción en un dispositivo.
        $this->artisan('zia:sync-telemetry')->assertExitCode(0);

        // La transacción debe haber revertido TODO: ni el last_raw_value ni
        // la TelemetryReading quedan persistidos — si no, el consumo de este
        // intervalo se pierde del total anual sin dejar rastro.
        $this->assertEquals(0, TelemetryReading::where('device_id', $device->id)->count());
        $this->assertEquals(5000.0, $device->fresh()->last_raw_value);
    }

    public function test_energy_sync_continues_with_next_device_after_one_fails()
    {
        $device1 = $this->makeDevice(['thingsboard_id' => 'device-1', 'last_raw_value' => 5000.0]);
        $device2 = $this->makeDevice(['thingsboard_id' => 'device-2', 'last_raw_value' => 1000.0]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldReceive('getLatestTelemetry')
                ->with('device-1', 'energy_active_import_wh')
                ->andThrow(new \RuntimeException('timeout de red'));
            $mock->shouldReceive('getLatestTelemetry')
                ->with('device-2', 'energy_active_import_wh')
                ->andReturn(['value' => 2000.0, 'timestamp' => now()->toDateTimeString(), 'is_fallback' => false]);
        });

        $this->artisan('zia:sync-telemetry')->assertExitCode(0);

        // device-1 falló, pero device-2 se procesó igual.
        $this->assertEquals(0, TelemetryReading::where('device_id', $device1->id)->count());
        $reading2 = TelemetryReading::where('device_id', $device2->id)->first();
        $this->assertNotNull($reading2);
        $this->assertEqualsWithDelta(1.0, $reading2->value, 0.0001); // (2000-1000)/1000
    }

    public function test_energy_sync_uses_device_unit_in_log_output()
    {
        $this->makeDevice(['unit' => 'MWh', 'last_raw_value' => 5000.0]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldReceive('getLatestTelemetry')
                ->andReturn(['value' => 6500.0, 'timestamp' => now()->toDateTimeString(), 'is_fallback' => false]);
        });

        $this->artisan('zia:sync-telemetry')
            ->expectsOutputToContain('MWh')
            ->assertExitCode(0);
    }

    // ─── residuos: sensor por evento ───────────────────────────────────────

    public function test_waste_first_sync_sets_watermark_without_processing_events()
    {
        $device = $this->makeDevice(['type' => 'waste', 'last_synced_at' => null]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldNotReceive('getTimeseriesRange');
        });

        $this->artisan('zia:sync-telemetry')->assertExitCode(0);

        $this->assertEquals(0, TelemetryReading::where('device_id', $device->id)->count());
        $this->assertNotNull($device->fresh()->last_synced_at);
    }

    public function test_waste_sync_creates_one_reading_per_event_in_range()
    {
        $lastSync = now()->subMinutes(15);
        $device = $this->makeDevice(['type' => 'waste', 'last_synced_at' => $lastSync]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldReceive('getTimeseriesRange')
                ->with('device-under-test', 'weight_kg', \Mockery::type('int'), \Mockery::type('int'))
                ->andReturn([
                    ['value' => 12.5, 'timestamp' => now()->subMinutes(10)->toDateTimeString()],
                    ['value' => 3.0, 'timestamp' => now()->subMinutes(2)->toDateTimeString()],
                ]);
        });

        $this->artisan('zia:sync-telemetry')->assertExitCode(0);

        $readings = TelemetryReading::where('device_id', $device->id)->orderBy('id')->get();
        $this->assertCount(2, $readings);
        $this->assertEqualsWithDelta(12.5, $readings[0]->value, 0.0001);
        $this->assertEqualsWithDelta(3.0, $readings[1]->value, 0.0001);
        $this->assertTrue($device->fresh()->last_synced_at->greaterThan($lastSync));
    }

    public function test_waste_sync_ingests_once_per_cycle_not_once_per_event()
    {
        $lastSync = now()->subMinutes(15);
        $this->makeDevice(['type' => 'waste', 'last_synced_at' => $lastSync]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldReceive('getTimeseriesRange')
                ->andReturn([
                    ['value' => 12.5, 'timestamp' => now()->subMinutes(10)->toDateTimeString()],
                    ['value' => 3.0, 'timestamp' => now()->subMinutes(5)->toDateTimeString()],
                    ['value' => 7.2, 'timestamp' => now()->subMinutes(2)->toDateTimeString()],
                ]);
        });
        // 3 eventos deben crear 3 TelemetryReading (para alertas/auditoría),
        // pero ingestReading — que re-suma TODO el año y hace upsert — debe
        // llamarse una sola vez por corrida, no una vez por evento.
        $this->mock(\App\Services\IoTCarbonIngestionService::class, function ($mock) {
            $mock->shouldReceive('ingestReading')->once()->andReturn(null);
        });

        $this->artisan('zia:sync-telemetry')->assertExitCode(0);
    }

    public function test_waste_sync_does_not_advance_watermark_on_range_failure()
    {
        $lastSync = now()->subMinutes(15);
        $device = $this->makeDevice(['type' => 'waste', 'last_synced_at' => $lastSync]);

        $this->mock(ThingsBoardService::class, function ($mock) {
            $mock->shouldReceive('getTimeseriesRange')->andReturn(null);
        });

        $this->artisan('zia:sync-telemetry')->assertExitCode(0);

        $this->assertEquals(0, TelemetryReading::where('device_id', $device->id)->count());
        $this->assertEqualsWithDelta(
            $lastSync->timestamp,
            $device->fresh()->last_synced_at->timestamp,
            1
        );
    }
}
