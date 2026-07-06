<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalCredentialControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Matches phpunit.xml env: INTERNAL_API_SECRET=test-secret-ci */
    private string $validSecret = 'test-secret-ci';

    public function test_rejects_request_without_internal_secret()
    {
        $this->getJson('/api/internal/credentials')->assertStatus(403);
    }

    public function test_rejects_request_with_wrong_internal_secret()
    {
        $this->withHeaders(['X-Internal-Secret' => 'wrong-secret'])
             ->getJson('/api/internal/credentials')
             ->assertStatus(403);
    }

    public function test_returns_db_values_when_configured()
    {
        SystemSetting::create(['key' => 'MISTRAL_API_KEY', 'value' => 'db-mistral-key']);
        SystemSetting::create(['key' => 'ANTHROPIC_API_KEY', 'value' => 'db-anthropic-key']);

        $response = $this->withHeaders(['X-Internal-Secret' => $this->validSecret])
             ->getJson('/api/internal/credentials');

        $response->assertOk()
                 ->assertJsonPath('mistral_api_key', 'db-mistral-key')
                 ->assertJsonPath('anthropic_api_key', 'db-anthropic-key');
    }

    public function test_returns_null_when_not_configured_in_db_never_falls_back_to_laravels_own_env()
    {
        // A propósito NO usa SystemSetting::resolve() (que caería al .env de
        // Laravel) — Laravel no tiene MISTRAL_API_KEY/ANTHROPIC_API_KEY en su
        // propio entorno (viven solo en el .env de zia-agent), así que ese
        // fallback reportaría un valor vacío y rompería la key real del
        // agente en cuanto se borre un override. null = "sin override, decide
        // tú (zia-agent) tu propio fallback con tu propio .env".
        $response = $this->withHeaders(['X-Internal-Secret' => $this->validSecret])
             ->getJson('/api/internal/credentials');

        $response->assertOk()
                 ->assertJsonPath('mistral_api_key', null)
                 ->assertJsonPath('anthropic_api_key', null)
                 ->assertJsonPath('langfuse_public_key', null)
                 ->assertJsonPath('langfuse_secret_key', null);
    }
}
