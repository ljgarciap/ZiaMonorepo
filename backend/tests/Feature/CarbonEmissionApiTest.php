<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
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
}
