<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\ApiKey;
use App\Models\Company;
use App\Models\IotDevice;
use App\Models\TelemetryReading;
use App\Models\CarbonEmission;
use App\Models\Period;
use App\Models\EmissionFactor;
use App\Models\EmissionCategory;
use App\Models\Scope;

class ExternalApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Company $otherCompany;
    private string $plainKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->otherCompany = Company::factory()->create();

        $result = ApiKey::generateFor($this->company, 'Integración de prueba');
        $this->plainKey = $result['key'];
    }

    private function withKey(string $key)
    {
        return $this->withHeaders(['X-Api-Key' => $key]);
    }

    // ─── autenticación ──────────────────────────────────────────────────────

    public function test_request_without_key_is_rejected(): void
    {
        $this->getJson('/api/external/v1/telemetry-readings')
             ->assertStatus(401);
    }

    public function test_request_with_invalid_key_is_rejected(): void
    {
        $this->withKey('zia_live_esto-no-existe')
             ->getJson('/api/external/v1/telemetry-readings')
             ->assertStatus(401);
    }

    public function test_request_with_revoked_key_is_rejected(): void
    {
        $result = ApiKey::generateFor($this->company, 'Key a revocar');
        $result['model']->update(['revoked_at' => now()]);

        $this->withKey($result['key'])
             ->getJson('/api/external/v1/telemetry-readings')
             ->assertStatus(401);
    }

    public function test_valid_key_authenticates_and_updates_last_used_at(): void
    {
        $apiKeyModel = ApiKey::where('key_hash', ApiKey::hash($this->plainKey))->first();
        $this->assertNull($apiKeyModel->last_used_at);

        $this->withKey($this->plainKey)
             ->getJson('/api/external/v1/telemetry-readings')
             ->assertOk();

        $this->assertNotNull($apiKeyModel->fresh()->last_used_at);
    }

    // ─── aislamiento de tenant ──────────────────────────────────────────────

    public function test_telemetry_readings_never_leak_across_companies(): void
    {
        $myDevice = IotDevice::factory()->create(['company_id' => $this->company->id]);
        $otherDevice = IotDevice::factory()->create(['company_id' => $this->otherCompany->id]);

        TelemetryReading::create([
            'device_id' => $myDevice->id, 'metric_name' => 'electricity_kwh',
            'value' => 10.0, 'timestamp' => now(),
        ]);
        TelemetryReading::create([
            'device_id' => $otherDevice->id, 'metric_name' => 'electricity_kwh',
            'value' => 999.0, 'timestamp' => now(),
        ]);

        $response = $this->withKey($this->plainKey)
             ->getJson('/api/external/v1/telemetry-readings')
             ->assertOk();

        $values = collect($response->json('data'))->pluck('value');
        $this->assertEquals([10.0], $values->all());
    }

    public function test_telemetry_readings_ignore_device_id_from_another_company(): void
    {
        $otherDevice = IotDevice::factory()->create(['company_id' => $this->otherCompany->id]);
        TelemetryReading::create([
            'device_id' => $otherDevice->id, 'metric_name' => 'electricity_kwh',
            'value' => 999.0, 'timestamp' => now(),
        ]);

        // Pedir explícitamente el device_id de OTRA empresa no debe filtrar nada.
        $this->withKey($this->plainKey)
             ->getJson("/api/external/v1/telemetry-readings?device_id={$otherDevice->id}")
             ->assertOk()
             ->assertJsonCount(0, 'data');
    }

    public function test_emissions_never_leak_across_companies(): void
    {
        $scope = Scope::firstOrCreate(['name' => 'Alcance 2'], ['description' => 'Scope 2']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);

        $myPeriod = Period::factory()->create(['company_id' => $this->company->id, 'year' => now()->year]);
        $otherPeriod = Period::factory()->create(['company_id' => $this->otherCompany->id, 'year' => now()->year]);

        CarbonEmission::create(['period_id' => $myPeriod->id, 'emission_factor_id' => $factor->id, 'quantity' => 5, 'calculated_co2e' => 0.5]);
        CarbonEmission::create(['period_id' => $otherPeriod->id, 'emission_factor_id' => $factor->id, 'quantity' => 999, 'calculated_co2e' => 99.9]);

        $response = $this->withKey($this->plainKey)
             ->getJson('/api/external/v1/emissions')
             ->assertOk();

        $quantities = collect($response->json('data'))->pluck('quantity')->map(fn ($v) => (float) $v);
        $this->assertEquals([5.0], $quantities->all());
    }

    // ─── filtros ────────────────────────────────────────────────────────────

    public function test_telemetry_readings_filter_by_metric_name(): void
    {
        $device = IotDevice::factory()->create(['company_id' => $this->company->id]);
        TelemetryReading::create(['device_id' => $device->id, 'metric_name' => 'electricity_kwh', 'value' => 1, 'timestamp' => now()]);
        TelemetryReading::create(['device_id' => $device->id, 'metric_name' => 'water_m3', 'value' => 2, 'timestamp' => now()]);

        $response = $this->withKey($this->plainKey)
             ->getJson('/api/external/v1/telemetry-readings?metric_name=water_m3')
             ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('water_m3', $response->json('data.0.metric_name'));
    }

    // ─── rate limit ─────────────────────────────────────────────────────────

    public function test_requests_are_throttled_per_api_key(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->withKey($this->plainKey)
                 ->getJson('/api/external/v1/telemetry-readings')
                 ->assertOk();
        }

        $this->withKey($this->plainKey)
             ->getJson('/api/external/v1/telemetry-readings')
             ->assertStatus(429);
    }
}
