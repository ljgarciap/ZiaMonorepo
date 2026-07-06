<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\EmissionFactor;
use App\Models\EmissionCategory;
use App\Models\Scope;

class AdminCompanyFactorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;
    private Company $company;
    private EmissionFactor $factorA;
    private EmissionFactor $factorB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->admin      = User::factory()->create(['role' => 'admin']);

        $scope          = Scope::firstOrCreate(['name' => 'Alcance 1'], ['description' => 'Directo']);
        $category       = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $this->factorA  = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);
        $this->factorB  = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);
        $this->company  = Company::factory()->create();
        $this->admin->companies()->attach($this->company->id, ['role' => 'admin', 'is_active' => true]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_admin_can_list_all_factors_with_enabled_status()
    {
        // Enable only factorA for this company
        $this->company->factors()->attach($this->factorA->id, ['is_enabled' => true]);

        $response = $this->actingAs($this->admin, 'api')
             ->getJson("/api/admin/companies/{$this->company->id}/factors");

        $response->assertOk();

        $items = collect($response->json());
        $a = $items->firstWhere('id', $this->factorA->id);
        $b = $items->firstWhere('id', $this->factorB->id);

        $this->assertTrue($a['is_enabled']);
        $this->assertFalse($b['is_enabled']);
    }

    public function test_all_factors_appear_even_if_none_enabled()
    {
        $response = $this->actingAs($this->admin, 'api')
             ->getJson("/api/admin/companies/{$this->company->id}/factors");

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($this->factorA->id));
        $this->assertTrue($ids->contains($this->factorB->id));
    }

    public function test_response_includes_category_name_and_unit_symbol()
    {
        $response = $this->actingAs($this->admin, 'api')
             ->getJson("/api/admin/companies/{$this->company->id}/factors")
             ->assertOk();

        $item = collect($response->json())->first();
        $this->assertArrayHasKey('category_name', $item);
        $this->assertArrayHasKey('unit_symbol', $item);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_admin_can_enable_and_disable_factors_for_company()
    {
        $payload = [
            'factors' => [
                ['id' => $this->factorA->id, 'is_enabled' => true],
                ['id' => $this->factorB->id, 'is_enabled' => false],
            ],
        ];

        $this->actingAs($this->admin, 'api')
             ->putJson("/api/admin/companies/{$this->company->id}/factors", $payload)
             ->assertOk()
             ->assertJsonFragment(['message' => "Factores actualizados correctamente para {$this->company->name}"]);

        // Only factorA should be in the pivot with is_enabled = true
        $this->assertDatabaseHas('company_emission_factor', [
            'company_id'        => $this->company->id,
            'emission_factor_id' => $this->factorA->id,
            'is_enabled'         => true,
        ]);
        $this->assertDatabaseHas('company_emission_factor', [
            'company_id'        => $this->company->id,
            'emission_factor_id' => $this->factorB->id,
            'is_enabled'         => false,
        ]);
    }

    public function test_update_factors_validates_required_structure()
    {
        $this->actingAs($this->admin, 'api')
             ->putJson("/api/admin/companies/{$this->company->id}/factors", [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['factors']);
    }

    public function test_update_factors_validates_each_item_has_id_and_is_enabled()
    {
        $this->actingAs($this->admin, 'api')
             ->putJson("/api/admin/companies/{$this->company->id}/factors", [
                 'factors' => [['name' => 'sin id ni is_enabled']],
             ])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['factors.0.id', 'factors.0.is_enabled']);
    }

    // ─── access control ──────────────────────────────────────────────────────

    public function test_regular_user_cannot_access_company_factors()
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'api')
             ->getJson("/api/admin/companies/{$this->company->id}/factors")
             ->assertForbidden();
    }

    // ─── IDOR: un admin no debe ver/editar factores de otra empresa ─────────

    public function test_admin_cannot_view_factors_of_another_company()
    {
        $otherCompany = Company::factory()->create();

        $this->actingAs($this->admin, 'api')
             ->getJson("/api/admin/companies/{$otherCompany->id}/factors")
             ->assertStatus(403);
    }

    public function test_admin_cannot_update_factors_of_another_company()
    {
        $otherCompany = Company::factory()->create();

        $this->actingAs($this->admin, 'api')
             ->putJson("/api/admin/companies/{$otherCompany->id}/factors", [
                 'factors' => [['id' => $this->factorA->id, 'is_enabled' => true]],
             ])
             ->assertStatus(403);

        $this->assertDatabaseMissing('company_emission_factor', [
            'company_id' => $otherCompany->id,
            'emission_factor_id' => $this->factorA->id,
        ]);
    }

    public function test_superadmin_can_view_factors_of_any_company()
    {
        $otherCompany = Company::factory()->create();

        $this->actingAs($this->superadmin, 'api')
             ->getJson("/api/admin/companies/{$otherCompany->id}/factors")
             ->assertOk();
    }
}
