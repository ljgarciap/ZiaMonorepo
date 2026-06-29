<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Period;
use App\Models\CarbonEmission;

class AISidecarControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create(['name' => 'Tech SA']);
        $this->user    = User::factory()->create(['role' => 'user']);
        $this->user->companies()->attach($this->company->id, ['role' => 'user', 'is_active' => true]);
    }

    // ─── POST /ai/chat ────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_chat()
    {
        $this->postJson('/api/ai/chat', ['message' => 'Hola', 'company_id' => 1])
             ->assertUnauthorized();
    }

    public function test_chat_validates_message_is_required()
    {
        $this->actingAs($this->user, 'api')
             ->postJson('/api/ai/chat', ['company_id' => $this->company->id])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['message']);
    }

    public function test_chat_validates_company_id_is_required()
    {
        $this->actingAs($this->user, 'api')
             ->postJson('/api/ai/chat', ['message' => 'Hola'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['company_id']);
    }

    public function test_chat_validates_company_must_exist()
    {
        $this->actingAs($this->user, 'api')
             ->postJson('/api/ai/chat', ['message' => 'Hola', 'company_id' => 99999])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['company_id']);
    }

    public function test_chat_validates_message_max_length()
    {
        $this->actingAs($this->user, 'api')
             ->postJson('/api/ai/chat', [
                 'message'    => str_repeat('a', 2001),
                 'company_id' => $this->company->id,
             ])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['message']);
    }

    public function test_chat_returns_sse_stream_when_agent_unavailable()
    {
        // Point to a closed port — fopen will fail immediately and return the error SSE event
        config(['services.zia_agent_url' => 'http://localhost:1']);

        $response = $this->actingAs($this->user, 'api')
             ->post('/api/ai/chat', [
                 'message'    => 'Hola ZIA',
                 'company_id' => $this->company->id,
             ]);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('error', $response->streamedContent());
    }

    // ─── GET /ai/recommendations ──────────────────────────────────────────────

    public function test_unauthenticated_cannot_get_recommendations()
    {
        $this->getJson('/api/ai/recommendations')
             ->assertUnauthorized();
    }

    public function test_recommendations_requires_company_context_header()
    {
        $this->actingAs($this->user, 'api')
             ->getJson('/api/ai/recommendations')
             ->assertStatus(400)
             ->assertJsonFragment(['error' => 'Company context header X-Company-Context is required']);
    }

    public function test_recommendations_returns_structure_for_valid_company()
    {
        $response = $this->actingAs($this->user, 'api')
             ->getJson('/api/ai/recommendations', [
                 'X-Company-Context' => (string) $this->company->id,
             ]);

        $response->assertOk()
                 ->assertJsonStructure(['company_name', 'recommendations', 'summary', 'timestamp'])
                 ->assertJsonPath('company_name', 'Tech SA');
    }

    public function test_recommendations_includes_recent_emissions_in_summary()
    {
        $period = Period::factory()->create(['company_id' => $this->company->id]);
        CarbonEmission::factory()->create([
            'period_id'      => $period->id,
            'calculated_co2e' => 2.5,
        ]);

        $response = $this->actingAs($this->user, 'api')
             ->getJson('/api/ai/recommendations', [
                 'X-Company-Context' => (string) $this->company->id,
             ]);

        $response->assertOk();
        $summary = $response->json('summary');
        $this->assertNotEmpty($summary);
        $this->assertArrayHasKey('calculated_co2e', $summary[0]);
    }
}
