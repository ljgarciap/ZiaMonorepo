<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\CompanySector;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTagControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    // ─── CRUD (superadmin) ──────────────────────────────────────────────────

    public function test_superadmin_can_list_tags()
    {
        Tag::factory()->count(2)->create();

        $this->actingAs($this->superadmin, 'api')
             ->getJson('/api/admin/tags')
             ->assertOk()
             ->assertJsonCount(2);
    }

    public function test_superadmin_can_create_global_tag()
    {
        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/tags', ['name' => 'ISO 14001']);

        $response->assertCreated()->assertJsonPath('name', 'ISO 14001');
        $this->assertDatabaseHas('tags', ['name' => 'ISO 14001', 'company_sector_id' => null]);
    }

    public function test_superadmin_can_create_sector_specific_tag()
    {
        $sector = CompanySector::create(['name' => 'Manufactura']);

        $response = $this->actingAs($this->superadmin, 'api')
             ->postJson('/api/admin/tags', ['name' => 'Emisiones fugitivas', 'company_sector_id' => $sector->id]);

        $response->assertCreated()->assertJsonPath('company_sector_id', $sector->id);
    }

    public function test_admin_cannot_manage_tag_catalog()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/tags', ['name' => 'No autorizado'])
             ->assertStatus(403);
    }

    public function test_superadmin_can_update_tag()
    {
        $tag = Tag::factory()->create(['name' => 'Original']);

        $this->actingAs($this->superadmin, 'api')
             ->putJson("/api/admin/tags/{$tag->id}", ['name' => 'Actualizado'])
             ->assertOk()
             ->assertJsonPath('name', 'Actualizado');
    }

    public function test_superadmin_can_toggle_tag()
    {
        $tag = Tag::factory()->create(['is_active' => true]);

        $this->actingAs($this->superadmin, 'api')
             ->postJson("/api/admin/tags/{$tag->id}/toggle")
             ->assertOk()
             ->assertJsonPath('is_active', false);
    }

    public function test_superadmin_can_delete_tag()
    {
        $tag = Tag::factory()->create();

        $this->actingAs($this->superadmin, 'api')
             ->deleteJson("/api/admin/tags/{$tag->id}")
             ->assertNoContent();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    // ─── availableForCompany (Admin: grupo de tags preconfigurados) ────────────

    public function test_available_tags_includes_global_and_matching_sector_tags()
    {
        $sector = CompanySector::create(['name' => 'Manufactura']);
        $otherSector = CompanySector::create(['name' => 'Servicios']);
        $company = Company::factory()->create(['company_sector_id' => $sector->id]);

        Tag::factory()->create(['name' => 'Global', 'company_sector_id' => null]);
        Tag::factory()->create(['name' => 'De mi sector', 'company_sector_id' => $sector->id]);
        Tag::factory()->create(['name' => 'De otro sector', 'company_sector_id' => $otherSector->id]);
        Tag::factory()->create(['name' => 'Inactiva', 'company_sector_id' => null, 'is_active' => false]);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->companies()->attach($company->id, ['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin, 'api')
             ->getJson("/api/companies/{$company->id}/available-tags");

        $response->assertOk()->assertJsonCount(2);
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Global'));
        $this->assertTrue($names->contains('De mi sector'));
        $this->assertFalse($names->contains('De otro sector'));
        $this->assertFalse($names->contains('Inactiva'));
    }
}
