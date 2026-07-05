<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\CarbonEmission;
use App\Models\User;
use App\Models\Company;
use App\Models\Period;
use App\Models\EmissionFactor;
use App\Models\EmissionCategory;

class CarbonEmissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $period;
    protected $factor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup authenticated user (if auth required)
        $this->user = User::factory()->create(); // Assuming User factory exists standard
        $this->actingAs($this->user, 'api');

        // Setup Data
        $company = Company::factory()->create();
        $this->period = Period::factory()->create(['company_id' => $company->id]);
        
        $category = EmissionCategory::factory()->create();
        $this->factor = EmissionFactor::factory()->create([
            'emission_category_id' => $category->id,
            'name' => 'Test Factor',
            'factor_co2' => 10.0, // Easy number
            'uncertainty_upper' => 5.0, // 5%
        ]);
    }

    public function test_can_add_emission_record()
    {
        $payload = [
            'emission_factor_id' => $this->factor->id,
            'quantity' => 100, // Total
            'monthly_inputs' => [100], // Single input
        ];

        $response = $this->postJson("/api/periods/{$this->period->id}/emissions", $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure(['id', 'calculated_co2e', 'uncertainty_result']);
                 
        // Verify Calculation
        // Activity = 100.
        // Factor CO2 = 10.
        // Emission CO2 = (100 * 10) / 1000 = 1.0 Tonne.
        // CO2e = 1.0 * 1 = 1.0.
        // Uncertainty: Act=0, Fact=5%. Combined=5%.
        
        $this->assertDatabaseHas('carbon_emissions', [
            'period_id' => $this->period->id,
            'calculated_co2e' => 1.0,
            'uncertainty_result' => 5.0,
        ]);
    }

    public function test_store_with_valid_data_returns_201_and_response_shape()
    {
        $payload = [
            'emission_factor_id' => $this->factor->id,
            'monthly_inputs'     => [50, 100, 150],
        ];

        $response = $this->postJson("/api/periods/{$this->period->id}/emissions", $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure(['id', 'calculated_co2e', 'uncertainty_result', 'period_id']);
    }

    public function test_store_with_missing_fields_returns_422()
    {
        // Neither quantity nor monthly_inputs provided
        $payload = [
            'emission_factor_id' => $this->factor->id,
        ];

        $response = $this->postJson("/api/periods/{$this->period->id}/emissions", $payload);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_request_returns_401()
    {
        // Reset auth guards so the request appears unauthenticated
        $this->app['auth']->forgetGuards();

        $response = $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $this->factor->id,
            'quantity'           => 100,
        ]);

        $response->assertStatus(401);
    }

    public function test_index_returns_emissions_for_period()
    {
        // Create one emission for the period
        $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $this->factor->id,
            'quantity'           => 100,
        ]);

        $response = $this->getJson("/api/periods/{$this->period->id}/emissions");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_delete_emission_returns_204()
    {
        // Delete requires admin or superadmin (matrix: Usuario = CRU, sin Delete)
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'api');

        $createResponse = $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $this->factor->id,
            'quantity'           => 100,
        ]);
        $emissionId = $createResponse->json('id');

        $response = $this->deleteJson("/api/emissions/{$emissionId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('carbon_emissions', ['id' => $emissionId, 'deleted_at' => null]);
    }

    public function test_delete_without_auth_returns_401()
    {
        // Create an emission while authenticated
        $createResponse = $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $this->factor->id,
            'quantity'           => 100,
        ]);
        $emissionId = $createResponse->json('id');

        // Reset auth guards to appear unauthenticated
        $this->app['auth']->forgetGuards();

        $response = $this->deleteJson("/api/emissions/{$emissionId}");

        $response->assertStatus(401);
    }

    public function test_history_with_search_filter()
    {
        // Create emission with a specifically named factor
        $namedFactor = EmissionFactor::factory()->create([
            'emission_category_id' => EmissionCategory::factory()->create()->id,
            'name'                 => 'Gasolina Especial',
            'factor_co2'           => 5.0,
            'uncertainty_upper'    => 2.0,
        ]);
        $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $namedFactor->id,
            'quantity'           => 100,
        ]);

        // Search by factor name
        $response = $this->getJson(
            "/api/companies/{$this->period->company_id}/emissions/history?search=Gasolina+Especial"
        );

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    public function test_history_with_sort_parameter()
    {
        // Create two emissions with different CO2e values
        $lowFactor = EmissionFactor::factory()->create([
            'emission_category_id' => EmissionCategory::factory()->create()->id,
            'factor_co2'           => 1.0,
            'uncertainty_upper'    => 0.0,
        ]);
        $highFactor = EmissionFactor::factory()->create([
            'emission_category_id' => EmissionCategory::factory()->create()->id,
            'factor_co2'           => 10.0,
            'uncertainty_upper'    => 0.0,
        ]);

        $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $lowFactor->id,
            'quantity'           => 100,
        ]);
        $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $highFactor->id,
            'quantity'           => 100,
        ]);

        $response = $this->getJson(
            "/api/companies/{$this->period->company_id}/emissions/history?sort_by=calculated_co2e&sort_dir=desc"
        );

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // First item should have higher co2e when sorted descending
        $this->assertGreaterThanOrEqual($data[1]['calculated_co2e'], $data[0]['calculated_co2e']);
    }

    public function test_history_with_page_2_returns_paginated_results()
    {
        // Create 12 emissions so that page 2 (per_page=10) has 2 records
        for ($i = 0; $i < 12; $i++) {
            $this->postJson("/api/periods/{$this->period->id}/emissions", [
                'emission_factor_id' => $this->factor->id,
                'quantity'           => 100,
            ]);
        }

        $response = $this->getJson(
            "/api/companies/{$this->period->company_id}/emissions/history?page=2&per_page=10"
        );

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'total', 'current_page', 'per_page']);
        $this->assertEquals(2, $response->json('current_page'));
        $this->assertCount(2, $response->json('data'));
    }

    // ─── 10-3: closed period validation ──────────────────────────────────────

    public function test_store_in_closed_period_returns_422()
    {
        $this->period->update(['status' => 'closed']);

        $response = $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $this->factor->id,
            'quantity'           => 100,
        ]);

        $response->assertStatus(422)
                 ->assertJsonFragment(['error' => 'No se pueden registrar emisiones en un período cerrado.']);
    }

    // ─── 10-4: multi-year comparison ─────────────────────────────────────────

    public function test_comparison_returns_scoped_co2e_grouped_by_year()
    {
        $yearA      = (int) $this->period->year;
        $yearB      = $yearA + 1;
        $companyId  = $this->period->company_id;

        $periodB = \App\Models\Period::factory()->create([
            'company_id' => $companyId,
            'year'       => $yearB,
        ]);

        // Emission in year A
        $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $this->factor->id,
            'quantity'           => 100,
        ]);

        // Emission in year B
        $this->postJson("/api/periods/{$periodB->id}/emissions", [
            'emission_factor_id' => $this->factor->id,
            'quantity'           => 200,
        ]);

        $response = $this->getJson(
            "/api/companies/{$companyId}/emissions/comparison?years[]={$yearA}&years[]={$yearB}"
        );

        $response->assertOk()
                 ->assertJsonStructure(['data' => [['year', 'scope1', 'scope2', 'scope3', 'total', 'biogenic_total']]]);

        $data = collect($response->json('data'));
        $this->assertCount(2, $data);

        // Both years use the same factor (Alcance 1) — total should equal scope1
        $rowA = $data->firstWhere('year', $yearA);
        $rowB = $data->firstWhere('year', $yearB);
        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);
        $this->assertEquals($rowA['total'], $rowA['scope1']);
        $this->assertGreaterThan($rowA['total'], $rowB['total']);
    }

    public function test_comparison_with_no_years_filter_returns_all_years()
    {
        $this->postJson("/api/periods/{$this->period->id}/emissions", [
            'emission_factor_id' => $this->factor->id,
            'quantity'           => 50,
        ]);

        $response = $this->getJson(
            "/api/companies/{$this->period->company_id}/emissions/comparison"
        );

        $response->assertOk()->assertJsonStructure(['data']);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // ─── destroy: protección de período cerrado (spec 1.2.3) ───────────────────

    public function test_admin_can_delete_emission_of_open_period()
    {
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'api')
             ->deleteJson("/api/emissions/{$emission->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('carbon_emissions', ['id' => $emission->id]);
    }

    public function test_admin_cannot_delete_emission_of_closed_period()
    {
        $this->period->update(['status' => 'closed']);
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'api')
             ->deleteJson("/api/emissions/{$emission->id}")
             ->assertStatus(403);

        $this->assertDatabaseHas('carbon_emissions', ['id' => $emission->id, 'deleted_at' => null]);
    }

    public function test_superadmin_can_delete_emission_of_closed_period()
    {
        $this->period->update(['status' => 'closed']);
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin, 'api')
             ->deleteJson("/api/emissions/{$emission->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('carbon_emissions', ['id' => $emission->id]);
    }

    public function test_deleting_emission_from_closed_period_is_logged_to_activity_log()
    {
        $this->period->update(['status' => 'closed']);
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin, 'api')
             ->deleteJson("/api/emissions/{$emission->id}");

        $this->assertDatabaseHas('activity_logs', [
            'model' => CarbonEmission::class,
            'model_id' => $emission->id,
            'action' => 'deleted',
            'user_id' => $superadmin->id,
        ]);
    }

    // ─── A09: validate/flag/reset-validation (solo Admin/Superadmin) ───────────

    public function test_admin_can_validate_emission()
    {
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'api')
             ->postJson("/api/emissions/{$emission->id}/validate", ['notes' => 'Revisado y correcto']);

        $response->assertOk()->assertJsonPath('validation_status', 'validated');

        $this->assertDatabaseHas('carbon_emissions', [
            'id'                 => $emission->id,
            'validation_status'  => 'validated',
            'validation_notes'   => 'Revisado y correcto',
            'validated_by'       => $admin->id,
        ]);
        $this->assertNotNull($emission->fresh()->validated_at);
    }

    public function test_superadmin_can_validate_emission()
    {
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin, 'api')
             ->postJson("/api/emissions/{$emission->id}/validate")
             ->assertOk()
             ->assertJsonPath('validation_status', 'validated');
    }

    public function test_user_role_cannot_validate_emission_returns_403()
    {
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'api')
             ->postJson("/api/emissions/{$emission->id}/validate")
             ->assertStatus(403);

        $this->assertDatabaseHas('carbon_emissions', [
            'id'                => $emission->id,
            'validation_status' => 'pending',
        ]);
    }

    public function test_auditor_role_cannot_validate_emission_returns_403()
    {
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $auditor = User::factory()->create(['role' => 'auditor']);

        $this->actingAs($auditor, 'api')
             ->postJson("/api/emissions/{$emission->id}/validate")
             ->assertStatus(403);
    }

    public function test_validate_without_auth_returns_401()
    {
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);

        $this->app['auth']->forgetGuards();

        $this->postJson("/api/emissions/{$emission->id}/validate")
             ->assertStatus(401);
    }

    public function test_admin_can_flag_emission_as_needs_review()
    {
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'api')
             ->postJson("/api/emissions/{$emission->id}/flag", ['notes' => 'Falta soporte documental']);

        $response->assertOk()->assertJsonPath('validation_status', 'needs_review');

        $this->assertDatabaseHas('carbon_emissions', [
            'id'                => $emission->id,
            'validation_status' => 'needs_review',
            'validation_notes'  => 'Falta soporte documental',
            'validated_by'      => $admin->id,
        ]);
    }

    public function test_user_role_cannot_flag_emission_returns_403()
    {
        $emission = CarbonEmission::factory()->create(['period_id' => $this->period->id]);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'api')
             ->postJson("/api/emissions/{$emission->id}/flag")
             ->assertStatus(403);
    }

    public function test_admin_can_reset_validation_to_pending()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $emission = CarbonEmission::factory()->create([
            'period_id'          => $this->period->id,
            'validation_status'  => 'validated',
            'validation_notes'   => 'Revisado',
            'validated_by'       => $admin->id,
            'validated_at'       => now(),
        ]);

        $response = $this->actingAs($admin, 'api')
             ->postJson("/api/emissions/{$emission->id}/reset-validation");

        $response->assertOk()->assertJsonPath('validation_status', 'pending');

        $this->assertDatabaseHas('carbon_emissions', [
            'id'                => $emission->id,
            'validation_status' => 'pending',
            'validation_notes'  => null,
            'validated_by'      => null,
            'validated_at'      => null,
        ]);
    }

    public function test_user_role_cannot_reset_validation_returns_403()
    {
        $emission = CarbonEmission::factory()->create([
            'period_id'         => $this->period->id,
            'validation_status' => 'validated',
        ]);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'api')
             ->postJson("/api/emissions/{$emission->id}/reset-validation")
             ->assertStatus(403);

        $this->assertDatabaseHas('carbon_emissions', [
            'id'                => $emission->id,
            'validation_status' => 'validated',
        ]);
    }
}
