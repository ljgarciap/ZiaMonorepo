<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiCredentialControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    public function test_admin_role_cannot_access_api_credentials()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'api')
             ->getJson('/api/admin/api-credentials')
             ->assertStatus(403);
    }

    public function test_index_lists_all_managed_keys_with_is_set_flag()
    {
        SystemSetting::create(['key' => 'MISTRAL_API_KEY', 'value' => 'sk-abc12345']);

        $response = $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/api-credentials');

        $response->assertOk();
        $keys = collect($response->json())->keyBy('key');

        $this->assertTrue($keys['MISTRAL_API_KEY']['is_set']);
        $this->assertEquals('*******2345', $keys['MISTRAL_API_KEY']['masked_value']);
        $this->assertFalse($keys['ANTHROPIC_API_KEY']['is_set']);
        $this->assertNull($keys['ANTHROPIC_API_KEY']['masked_value']);

        // Never leaks the raw secret to the frontend
        $this->assertStringNotContainsString('sk-abc12345', $response->getContent());
    }

    public function test_update_creates_a_new_credential()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->putJson('/api/admin/api-credentials/MISTRAL_API_KEY', ['value' => 'sk-new-key-9999']);

        $response->assertOk()->assertJsonPath('is_set', true);
        $this->assertEquals('sk-new-key-9999', SystemSetting::resolve('MISTRAL_API_KEY'));

        $setting = SystemSetting::where('key', 'MISTRAL_API_KEY')->first();
        $this->assertEquals($this->superadmin->id, $setting->updated_by);
    }

    public function test_update_overwrites_an_existing_credential()
    {
        SystemSetting::create(['key' => 'MISTRAL_API_KEY', 'value' => 'old-value']);

        $this->actingAs($this->superadmin, 'api')
             ->putJson('/api/admin/api-credentials/MISTRAL_API_KEY', ['value' => 'new-value'])
             ->assertOk();

        $this->assertEquals('new-value', SystemSetting::resolve('MISTRAL_API_KEY'));
        $this->assertCount(1, SystemSetting::where('key', 'MISTRAL_API_KEY')->get());
    }

    public function test_update_rejects_a_key_not_in_the_managed_list()
    {
        $this->actingAs($this->superadmin, 'api')
             ->putJson('/api/admin/api-credentials/APP_KEY', ['value' => 'hijack-attempt'])
             ->assertStatus(422);

        $this->assertDatabaseMissing('system_settings', ['key' => 'APP_KEY']);
    }

    public function test_update_requires_a_non_empty_value()
    {
        $this->actingAs($this->superadmin, 'api')
             ->putJson('/api/admin/api-credentials/MISTRAL_API_KEY', ['value' => ''])
             ->assertStatus(422);
    }

    public function test_destroy_removes_the_credential_and_falls_back_to_env()
    {
        SystemSetting::create(['key' => 'MISTRAL_API_KEY', 'value' => 'db-value']);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson('/api/admin/api-credentials/MISTRAL_API_KEY')
             ->assertNoContent();

        $this->assertDatabaseMissing('system_settings', ['key' => 'MISTRAL_API_KEY']);
    }

    public function test_destroy_rejects_a_key_not_in_the_managed_list()
    {
        $this->actingAs($this->superadmin, 'api')
             ->deleteJson('/api/admin/api-credentials/APP_KEY')
             ->assertStatus(422);
    }
}
