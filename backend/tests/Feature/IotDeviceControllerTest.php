<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySector;
use App\Models\IotDevice;
use App\Models\OperationalUnit;
use App\Models\TelemetryAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IotDeviceControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Company $otherCompany;
    private User $tech;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $this->company      = Company::factory()->create(['company_sector_id' => $sector->id]);
        $this->otherCompany = Company::factory()->create(['company_sector_id' => $sector->id]);

        $this->tech = User::factory()->create(['role' => 'iot_tech']);
        $this->tech->companies()->attach($this->company->id, ['role' => 'iot_tech', 'is_active' => true]);
    }

    // ─── index / scoping ────────────────────────────────────────────────────

    public function test_iot_tech_can_list_devices_of_assigned_company(): void
    {
        IotDevice::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->tech, 'api')
             ->getJson("/api/companies/{$this->company->id}/iot-devices")
             ->assertOk()
             ->assertJsonCount(1);
    }

    public function test_iot_tech_cannot_list_devices_of_unassigned_company(): void
    {
        IotDevice::factory()->create(['company_id' => $this->otherCompany->id]);

        $this->actingAs($this->tech, 'api')
             ->getJson("/api/companies/{$this->otherCompany->id}/iot-devices")
             ->assertStatus(403);
    }

    public function test_user_role_cannot_access_device_registry(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $user->companies()->attach($this->company->id, ['role' => 'user', 'is_active' => true]);

        $this->actingAs($user, 'api')
             ->getJson("/api/companies/{$this->company->id}/iot-devices")
             ->assertStatus(403);
    }

    public function test_superadmin_can_list_devices_of_any_company(): void
    {
        IotDevice::factory()->create(['company_id' => $this->otherCompany->id]);
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin, 'api')
             ->getJson("/api/companies/{$this->otherCompany->id}/iot-devices")
             ->assertOk()
             ->assertJsonCount(1);
    }

    // ─── store ──────────────────────────────────────────────────────────────

    public function test_iot_tech_can_register_device_in_assigned_company(): void
    {
        $response = $this->actingAs($this->tech, 'api')
             ->postJson("/api/companies/{$this->company->id}/iot-devices", [
                 'name' => 'Medidor Piso 3',
                 'type' => 'energy',
                 'location' => 'Piso 3',
                 'unit' => 'kWh',
             ]);

        $response->assertCreated()
                 ->assertJsonPath('name', 'Medidor Piso 3')
                 ->assertJsonPath('registered_by', $this->tech->id);

        $this->assertDatabaseHas('iot_devices', [
            'name' => 'Medidor Piso 3',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_iot_tech_cannot_register_device_in_unassigned_company(): void
    {
        $this->actingAs($this->tech, 'api')
             ->postJson("/api/companies/{$this->otherCompany->id}/iot-devices", [
                 'name' => 'Medidor', 'type' => 'energy',
             ])
             ->assertStatus(403);
    }

    public function test_admin_cannot_register_device(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->companies()->attach($this->company->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'api')
             ->postJson("/api/companies/{$this->company->id}/iot-devices", [
                 'name' => 'Medidor', 'type' => 'energy',
             ])
             ->assertStatus(403);
    }

    // ─── update / destroy ───────────────────────────────────────────────────

    public function test_iot_tech_can_update_device_of_assigned_company(): void
    {
        $device = IotDevice::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->tech, 'api')
             ->putJson("/api/iot-devices/{$device->id}", ['location' => 'Piso 5'])
             ->assertOk()
             ->assertJsonPath('location', 'Piso 5');
    }

    public function test_iot_tech_cannot_update_device_of_unassigned_company(): void
    {
        $device = IotDevice::factory()->create(['company_id' => $this->otherCompany->id]);

        $this->actingAs($this->tech, 'api')
             ->putJson("/api/iot-devices/{$device->id}", ['location' => 'Piso 5'])
             ->assertStatus(403);
    }

    public function test_iot_tech_can_delete_device_of_assigned_company(): void
    {
        $device = IotDevice::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->tech, 'api')
             ->deleteJson("/api/iot-devices/{$device->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('iot_devices', ['id' => $device->id]);
    }

    // ─── calibrate ──────────────────────────────────────────────────────────

    public function test_iot_tech_can_register_calibration(): void
    {
        $device = IotDevice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->tech, 'api')
             ->postJson("/api/iot-devices/{$device->id}/calibrate", ['notes' => 'Sensor recalibrado OK']);

        $response->assertOk()
                 ->assertJsonPath('calibration_notes', 'Sensor recalibrado OK');

        $this->assertNotNull($response->json('last_calibrated_at'));
    }

    // ─── resolveAlert ───────────────────────────────────────────────────────

    public function test_iot_tech_can_resolve_alert_of_assigned_company_device(): void
    {
        $device = IotDevice::factory()->create(['company_id' => $this->company->id]);
        $alert = TelemetryAlert::create([
            'device_id' => $device->id,
            'alert_type' => 'off_hours_excess',
            'severity' => 'warning',
            'message' => 'Consumo fuera de horario',
            'threshold_value' => 10,
            'actual_value' => 25,
            'detected_at' => now(),
            'resolved' => false,
        ]);

        $response = $this->actingAs($this->tech, 'api')
             ->postJson("/api/telemetry/alerts/{$alert->id}/resolve", [
                 'resolution_note' => 'Falso positivo: mantenimiento programado',
             ]);

        $response->assertOk()->assertJsonPath('resolved', true);

        $alert->refresh();
        $this->assertTrue($alert->resolved);
        $this->assertSame($this->tech->id, $alert->resolved_by);
        $this->assertNotNull($alert->resolved_at);
    }

    public function test_iot_tech_cannot_resolve_alert_of_unassigned_company_device(): void
    {
        $device = IotDevice::factory()->create(['company_id' => $this->otherCompany->id]);
        $alert = TelemetryAlert::create([
            'device_id' => $device->id,
            'alert_type' => 'off_hours_excess',
            'severity' => 'warning',
            'message' => 'Consumo fuera de horario',
            'threshold_value' => 10,
            'actual_value' => 25,
            'detected_at' => now(),
            'resolved' => false,
        ]);

        $this->actingAs($this->tech, 'api')
             ->postJson("/api/telemetry/alerts/{$alert->id}/resolve", [])
             ->assertStatus(403);
    }

    // ─── operational_unit_id (gap: "asociar dispositivo a unidad operativa") ──

    public function test_iot_tech_can_assign_device_to_unit_of_same_company(): void
    {
        $unit = OperationalUnit::create(['company_id' => $this->company->id, 'name' => 'Piso 3']);
        $device = IotDevice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->tech, 'api')
             ->putJson("/api/iot-devices/{$device->id}", ['operational_unit_id' => $unit->id]);

        $response->assertOk()->assertJsonPath('operational_unit_id', $unit->id);
    }

    public function test_cannot_assign_device_to_unit_of_another_company(): void
    {
        $foreignUnit = OperationalUnit::create(['company_id' => $this->otherCompany->id, 'name' => 'Otra empresa']);
        $device = IotDevice::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->tech, 'api')
             ->putJson("/api/iot-devices/{$device->id}", ['operational_unit_id' => $foreignUnit->id])
             ->assertStatus(422);
    }

    // ─── trazabilidad: cambios de dispositivo quedan en la bitácora ────────────

    public function test_device_creation_and_update_are_logged_to_activity_log(): void
    {
        $response = $this->actingAs($this->tech, 'api')
             ->postJson("/api/companies/{$this->company->id}/iot-devices", [
                 'name' => 'Medidor Piso 2', 'type' => 'energy',
             ]);

        $deviceId = $response->json('id');

        $this->assertDatabaseHas('activity_logs', [
            'model' => IotDevice::class,
            'model_id' => $deviceId,
            'action' => 'created',
        ]);

        $this->actingAs($this->tech, 'api')
             ->putJson("/api/iot-devices/{$deviceId}", ['location' => 'Piso 2 - Ala Norte']);

        $log = ActivityLog::where('model', IotDevice::class)
            ->where('model_id', $deviceId)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Piso 2 - Ala Norte', $log->details['new']['location']);
    }
}
