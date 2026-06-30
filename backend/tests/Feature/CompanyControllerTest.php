<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySector;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $user;
    private CompanySector $sector;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector    = CompanySector::create(['name' => 'Servicios Test']);
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->user       = User::factory()->create(['role' => 'user']);
        $this->company    = Company::factory()->create(['company_sector_id' => $this->sector->id]);
    }

    // ─── GET /api/companies ───────────────────────────────────────────────────

    public function test_unauthenticated_cannot_list_companies(): void
    {
        $this->getJson('/api/companies')->assertStatus(401);
    }

    public function test_superadmin_sees_all_companies(): void
    {
        $other = Company::factory()->create(['company_sector_id' => $this->sector->id]);

        $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/companies')
             ->assertOk()
             ->assertJsonCount(2);
    }

    public function test_user_sees_only_their_active_companies(): void
    {
        $activeCompany   = Company::factory()->create(['company_sector_id' => $this->sector->id]);
        $inactiveCompany = Company::factory()->create(['company_sector_id' => $this->sector->id]);

        $this->user->companies()->attach($activeCompany->id,   ['role' => 'user', 'is_active' => true]);
        $this->user->companies()->attach($inactiveCompany->id, ['role' => 'user', 'is_active' => false]);

        $response = $this->actingAs($this->user, 'api')
                         ->getJson('/api/companies')
                         ->assertOk();

        $ids = collect($response->json())->pluck('id');
        $this->assertContains($activeCompany->id,   $ids->all());
        $this->assertNotContains($inactiveCompany->id, $ids->all());
    }

    public function test_user_does_not_see_companies_they_dont_belong_to(): void
    {
        $myCompany    = Company::factory()->create(['company_sector_id' => $this->sector->id]);
        $otherCompany = Company::factory()->create(['company_sector_id' => $this->sector->id]);

        $this->user->companies()->attach($myCompany->id, ['role' => 'user', 'is_active' => true]);

        $response = $this->actingAs($this->user, 'api')
                         ->getJson('/api/companies')
                         ->assertOk();

        $ids = collect($response->json())->pluck('id');
        $this->assertContains($myCompany->id,      $ids->all());
        $this->assertNotContains($otherCompany->id, $ids->all());
    }

    public function test_company_list_includes_sector_relation(): void
    {
        $this->user->companies()->attach($this->company->id, ['role' => 'user', 'is_active' => true]);

        $response = $this->actingAs($this->user, 'api')
                         ->getJson('/api/companies')
                         ->assertOk();

        $this->assertArrayHasKey('sector', $response->json()[0]);
        $this->assertEquals('Servicios Test', $response->json()[0]['sector']['name']);
    }

    // ─── GET /api/companies/{company}/periods ────────────────────────────────

    public function test_unauthenticated_cannot_list_periods(): void
    {
        $this->getJson("/api/companies/{$this->company->id}/periods")->assertStatus(401);
    }

    public function test_company_not_found_returns_404(): void
    {
        $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/companies/99999/periods')
             ->assertStatus(404);
    }

    public function test_user_without_access_gets_403(): void
    {
        // user has no pivot record for $this->company
        $this->actingAs($this->user, 'api')
             ->getJson("/api/companies/{$this->company->id}/periods")
             ->assertStatus(403);
    }

    public function test_user_with_access_sees_periods_ordered_desc(): void
    {
        $this->user->companies()->attach($this->company->id, ['role' => 'user', 'is_active' => true]);

        Period::factory()->create(['company_id' => $this->company->id, 'year' => 2022]);
        Period::factory()->create(['company_id' => $this->company->id, 'year' => 2024]);
        Period::factory()->create(['company_id' => $this->company->id, 'year' => 2023]);

        $response = $this->actingAs($this->user, 'api')
                         ->getJson("/api/companies/{$this->company->id}/periods")
                         ->assertOk();

        $years = collect($response->json())->pluck('year')->all();
        $this->assertEquals([2024, 2023, 2022], $years);
    }

    public function test_superadmin_can_see_periods_of_any_company(): void
    {
        Period::factory()->create(['company_id' => $this->company->id, 'year' => 2024]);

        $this->actingAs($this->superadmin, 'api')
             ->getJson("/api/companies/{$this->company->id}/periods")
             ->assertOk()
             ->assertJsonCount(1);
    }
}
