<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\CalculationFormula;

class CalculationFormulaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    public function test_superadmin_can_list_formulas()
    {
        CalculationFormula::create(['name' => 'Combustión Estándar', 'expression' => '(activity_data * factor_co2) / 1000']);
        CalculationFormula::create(['name' => 'Combustión Móvil', 'expression' => '(activity_data * factor_co2) / 1000']);

        $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/formulas')
             ->assertOk()
             ->assertJsonCount(2);
    }

    public function test_superadmin_can_create_formula()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/formulas', [
                 'name'        => 'Fugas de Refrigerante',
                 'expression'  => '(activity_data * factor_total_co2e) / 1000',
                 'description' => 'Para gases refrigerantes con GWP directo',
             ]);

        $response->assertCreated()
                 ->assertJsonPath('name', 'Fugas de Refrigerante');

        $this->assertDatabaseHas('calculation_formulas', ['name' => 'Fugas de Refrigerante']);
    }

    public function test_create_formula_requires_name_and_expression()
    {
        // Controller uses Validator::make — response: {"name": [...], "expression": [...]}
        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/formulas', ['description' => 'Sin nombre ni expresión'])
             ->assertUnprocessable()
             ->assertJsonStructure(['name', 'expression']);
    }

    public function test_create_formula_validates_unique_name()
    {
        CalculationFormula::create(['name' => 'Duplicado', 'expression' => 'x']);

        $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/formulas', ['name' => 'Duplicado', 'expression' => 'y'])
             ->assertUnprocessable()
             ->assertJsonStructure(['name']);
    }

    public function test_superadmin_can_show_formula()
    {
        $formula = CalculationFormula::create([
            'name'       => 'Formula Test',
            'expression' => '(activity_data * factor_co2) / 1000',
        ]);

        $this->actingAs($this->superadmin, 'api')
             ->getJson("/api/admin/formulas/{$formula->id}")
             ->assertOk()
             ->assertJsonPath('expression', '(activity_data * factor_co2) / 1000');
    }

    public function test_superadmin_can_update_formula()
    {
        $formula = CalculationFormula::create(['name' => 'Original', 'expression' => 'a']);

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/formulas/{$formula->id}", [
                 'name'       => 'Actualizado',
                 'expression' => '(activity_data * factor_total_co2e) / 1000',
             ])
             ->assertOk()
             ->assertJsonPath('name', 'Actualizado');
    }

    public function test_update_formula_name_unique_ignores_self()
    {
        $formula = CalculationFormula::create(['name' => 'Mi Formula', 'expression' => 'a']);

        // Updating with the same name should not fail uniqueness
        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/formulas/{$formula->id}", [
                 'name'       => 'Mi Formula',
                 'expression' => 'b',
             ])
             ->assertOk();
    }

    public function test_superadmin_can_delete_formula()
    {
        $formula = CalculationFormula::create(['name' => 'Para borrar', 'expression' => 'x']);

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/formulas/{$formula->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('calculation_formulas', ['id' => $formula->id]);
    }

    public function test_admin_cannot_access_formula_endpoints()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'api')
             ->getJson('/api/admin/formulas')
             ->assertForbidden();
    }
}
