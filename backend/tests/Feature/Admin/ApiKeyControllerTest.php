<?php

namespace Tests\Feature\Admin;

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\CompanySector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Company $otherCompany;
    private User $admin;
    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $this->company      = Company::factory()->create(['company_sector_id' => $sector->id]);
        $this->otherCompany = Company::factory()->create(['company_sector_id' => $sector->id]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->companies()->attach($this->company->id, ['role' => 'admin', 'is_active' => true]);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    public function test_admin_can_create_key_for_assigned_company_and_sees_plaintext_once(): void
    {
        $response = $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$this->company->id}/api-keys", ['name' => 'Integración X'])
             ->assertCreated();

        $this->assertStringStartsWith('zia_live_', $response->json('key'));
        $this->assertDatabaseHas('api_keys', ['company_id' => $this->company->id, 'name' => 'Integración X']);
    }

    public function test_admin_cannot_create_key_for_unassigned_company(): void
    {
        $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$this->otherCompany->id}/api-keys", ['name' => 'x'])
             ->assertStatus(403);
    }

    public function test_superadmin_can_create_key_for_any_company(): void
    {
        $this->actingAs($this->superadmin, 'api')
             ->postJson("/api/admin/companies/{$this->otherCompany->id}/api-keys", ['name' => 'x'])
             ->assertCreated();
    }

    public function test_index_never_exposes_key_hash_or_plaintext(): void
    {
        ApiKey::generateFor($this->company, 'Key existente');

        $response = $this->actingAs($this->admin, 'api')
             ->getJson("/api/admin/companies/{$this->company->id}/api-keys")
             ->assertOk();

        $response->assertJsonMissingPath('0.key_hash');
        $response->assertJsonMissingPath('0.key');
        $this->assertNotEmpty($response->json('0.key_prefix'));
    }

    public function test_admin_can_revoke_key_of_assigned_company(): void
    {
        $result = ApiKey::generateFor($this->company, 'A revocar');

        $this->actingAs($this->admin, 'api')
             ->deleteJson("/api/admin/api-keys/{$result['model']->id}")
             ->assertStatus(204);

        $this->assertNotNull($result['model']->fresh()->revoked_at);
    }

    public function test_revoked_key_stops_authenticating_on_external_api_immediately(): void
    {
        $result = ApiKey::generateFor($this->company, 'A revocar');

        $this->actingAs($this->admin, 'api')
             ->deleteJson("/api/admin/api-keys/{$result['model']->id}")
             ->assertStatus(204);

        $this->withHeaders(['X-Api-Key' => $result['key']])
             ->getJson('/api/external/v1/telemetry-readings')
             ->assertStatus(401);
    }

    public function test_admin_cannot_revoke_key_of_unassigned_company(): void
    {
        $result = ApiKey::generateFor($this->otherCompany, 'De otra empresa');

        $this->actingAs($this->admin, 'api')
             ->deleteJson("/api/admin/api-keys/{$result['model']->id}")
             ->assertStatus(403);
    }
}
