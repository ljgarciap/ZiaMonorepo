<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\EmissionFactor;
use App\Models\EmissionCategory;

class InternalCalculationControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Matches phpunit.xml env: INTERNAL_API_SECRET=test-secret-ci */
    private string $validSecret = 'test-secret-ci';

    private EmissionFactor $factor;

    protected function setUp(): void
    {
        parent::setUp();

        $category    = EmissionCategory::factory()->create();
        $this->factor = EmissionFactor::factory()->create([
            'emission_category_id' => $category->id,
            'factor_co2'           => 10.0,
            'factor_ch4'           => 0.5,
            'factor_n2o'           => 0.1,
            'factor_nf3'           => 0.0,
            'factor_sf6'           => 0.0,
            'factor_total_co2e'    => 0.0,
            'uncertainty_upper'    => 5.0,
        ]);
    }

    public function test_request_without_secret_returns_403()
    {
        $response = $this->postJson('/api/internal/calculate', [
            'emission_factor_id' => $this->factor->id,
            'monthly_values'     => [100],
        ]);

        $response->assertStatus(403)
                 ->assertJson(['error' => 'Forbidden']);
    }

    public function test_request_with_wrong_secret_returns_403()
    {
        $response = $this->withHeaders(['X-Internal-Secret' => 'wrong-secret'])
                         ->postJson('/api/internal/calculate', [
                             'emission_factor_id' => $this->factor->id,
                             'monthly_values'     => [100],
                         ]);

        $response->assertStatus(403)
                 ->assertJson(['error' => 'Forbidden']);
    }

    public function test_valid_request_returns_calculation()
    {
        $response = $this->withHeaders(['X-Internal-Secret' => $this->validSecret])
                         ->postJson('/api/internal/calculate', [
                             'emission_factor_id' => $this->factor->id,
                             'monthly_values'     => [100, 100],
                         ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['calculated_co2e', 'unit', 'factor_name']);

        // Verify the calculation is non-negative
        $this->assertGreaterThanOrEqual(0, $response->json('calculated_co2e'));
    }

    public function test_invalid_body_returns_422()
    {
        // Missing required field 'monthly_values'
        $response = $this->withHeaders(['X-Internal-Secret' => $this->validSecret])
                         ->postJson('/api/internal/calculate', [
                             'emission_factor_id' => $this->factor->id,
                             // monthly_values intentionally omitted
                         ]);

        $response->assertStatus(422);
    }

    public function test_response_includes_all_gas_components()
    {
        $response = $this->withHeaders(['X-Internal-Secret' => $this->validSecret])
                         ->postJson('/api/internal/calculate', [
                             'emission_factor_id' => $this->factor->id,
                             'monthly_values'     => [200],
                         ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'emissions_co2',
                     'emissions_ch4',
                     'emissions_n2o',
                     'calculated_co2e',
                     'factor_name',
                     'unit',
                 ]);
    }
}
