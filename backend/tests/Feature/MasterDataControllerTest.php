<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\EmissionFactor;
use App\Models\SectorQuestionnaireRule;

class MasterDataControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user, 'api');
    }

    public function test_questionnaire_returns_rules_for_sector()
    {
        $factor = EmissionFactor::factory()->create();

        SectorQuestionnaireRule::create([
            'sector_code'         => 'servicios',
            'emission_factor_id'  => $factor->id,
            'questionnaire_label' => 'Consumo eléctrico mensual',
            'variable_name'       => 'consumo_electrico',
            'input_unit_hint'     => 'kWh',
            'is_required'         => true,
            'display_order'       => 1,
        ]);

        $response = $this->getJson('/api/dictionaries/questionnaire?sector=servicios');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertNotEmpty($response->json(), 'Response must return at least one rule for sector=servicios');
        $this->assertEquals('servicios', 'servicios'); // sanity
        $this->assertArrayHasKey('emission_factor_id', $response->json()[0]);
    }

    public function test_questionnaire_returns_empty_for_unknown_sector()
    {
        $response = $this->getJson('/api/dictionaries/questionnaire?sector=nonexistent_sector_xyz');

        // Must return 200 with empty array (not 500)
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertEmpty($response->json());
    }

    public function test_emission_factors_returns_hierarchy()
    {
        // Create a full hierarchy: Scope → Category → Factor
        $scope    = \App\Models\Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $category = \App\Models\EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        EmissionFactor::factory()->create(['emission_category_id' => $category->id]);

        $response = $this->getJson('/api/dictionaries/factors');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        // Response should include the scope we created
        $this->assertNotEmpty($response->json());
    }

    public function test_emission_factors_with_scope_id_param_returns_200()
    {
        // The endpoint does not filter by scope_id but must not crash when the param is provided
        $response = $this->getJson('/api/dictionaries/factors?scope_id=1');

        // Must return 200 — not a 500 from an unexpected parameter
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    public function test_emission_factors_hides_disabled_factor_for_company()
    {
        $scope    = \App\Models\Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $category = \App\Models\EmissionCategory::factory()->create(['scope_id' => $scope->id, 'parent_id' => null]);
        $company  = \App\Models\Company::factory()->create();

        $enabled  = EmissionFactor::factory()->create(['emission_category_id' => $category->id, 'name' => 'Habilitado']);
        $disabled = EmissionFactor::factory()->create(['emission_category_id' => $category->id, 'name' => 'Deshabilitado']);

        // Attach both factors to the company; only $disabled has is_enabled=false
        $company->factors()->attach($enabled->id,  ['is_enabled' => true]);
        $company->factors()->attach($disabled->id, ['is_enabled' => false]);

        $response = $this->getJson("/api/dictionaries/factors?company_id={$company->id}");

        $response->assertStatus(200);

        $allFactorNames = collect($response->json())
            ->flatMap(fn($scope) => $scope['categories'])
            ->flatMap(fn($cat) => $cat['factors'])
            ->pluck('name');

        $this->assertContains('Habilitado',    $allFactorNames->all());
        $this->assertNotContains('Deshabilitado', $allFactorNames->all());
    }

    public function test_emission_factors_shows_all_when_no_company_id()
    {
        $scope    = \App\Models\Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Scope 1']);
        $category = \App\Models\EmissionCategory::factory()->create(['scope_id' => $scope->id, 'parent_id' => null]);

        EmissionFactor::factory()->create(['emission_category_id' => $category->id, 'name' => 'FactorGlobal']);

        $response = $this->getJson('/api/dictionaries/factors');

        $response->assertStatus(200);

        $allFactorNames = collect($response->json())
            ->flatMap(fn($scope) => $scope['categories'])
            ->flatMap(fn($cat) => $cat['factors'])
            ->pluck('name');

        $this->assertContains('FactorGlobal', $allFactorNames->all());
    }

    // --- Gap-7: questionnaire respects company is_enabled ---

    public function test_questionnaire_hides_disabled_factor_for_company()
    {
        $company = \App\Models\Company::factory()->create();

        $enabled  = EmissionFactor::factory()->create(['name' => 'FactorActivo']);
        $disabled = EmissionFactor::factory()->create(['name' => 'FactorInactivo']);

        $company->factors()->attach($disabled->id, ['is_enabled' => false]);

        foreach ([$enabled, $disabled] as $factor) {
            SectorQuestionnaireRule::create([
                'sector_code'         => 'servicios',
                'emission_factor_id'  => $factor->id,
                'questionnaire_label' => "Pregunta {$factor->name}",
                'variable_name'       => 'var_' . $factor->id,
                'is_required'         => true,
                'display_order'       => 1,
            ]);
        }

        $response = $this->getJson("/api/dictionaries/questionnaire?sector=servicios&company_id={$company->id}");

        $response->assertStatus(200);
        $labels = collect($response->json())->pluck('questionnaire_label');

        $this->assertContains('Pregunta FactorActivo',   $labels->all());
        $this->assertNotContains('Pregunta FactorInactivo', $labels->all());
    }

    public function test_questionnaire_shows_factor_with_no_pivot_record_as_enabled()
    {
        $company = \App\Models\Company::factory()->create();
        $factor  = EmissionFactor::factory()->create(['name' => 'FactorSinPivot']);

        // No company_emission_factor record → default enabled
        SectorQuestionnaireRule::create([
            'sector_code'         => 'servicios',
            'emission_factor_id'  => $factor->id,
            'questionnaire_label' => 'Pregunta sin pivot',
            'variable_name'       => 'var_sin_pivot',
            'is_required'         => false,
            'display_order'       => 1,
        ]);

        $response = $this->getJson("/api/dictionaries/questionnaire?sector=servicios&company_id={$company->id}");

        $response->assertStatus(200);
        $labels = collect($response->json())->pluck('questionnaire_label');
        $this->assertContains('Pregunta sin pivot', $labels->all());
    }

    public function test_questionnaire_without_company_id_returns_all_factors()
    {
        $company = \App\Models\Company::factory()->create();
        $factor  = EmissionFactor::factory()->create(['name' => 'FactorDeshabilitado']);

        $company->factors()->attach($factor->id, ['is_enabled' => false]);

        SectorQuestionnaireRule::create([
            'sector_code'         => 'servicios',
            'emission_factor_id'  => $factor->id,
            'questionnaire_label' => 'Pregunta global',
            'variable_name'       => 'var_global',
            'is_required'         => false,
            'display_order'       => 1,
        ]);

        // Without company_id, no filtering — disabled factors still appear
        $response = $this->getJson('/api/dictionaries/questionnaire?sector=servicios');

        $response->assertStatus(200);
        $labels = collect($response->json())->pluck('questionnaire_label');
        $this->assertContains('Pregunta global', $labels->all());
    }
}
