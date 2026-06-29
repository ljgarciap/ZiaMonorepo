<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;

class AdminCompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_sees_all_companies()
    {
        Company::factory()->count(3)->create();

        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($superadmin, 'api');

        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_admin_sees_only_their_own_companies()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $admin = User::factory()->create(['role' => 'admin']);
        // Attach only company1 to the admin via pivot
        $admin->companies()->attach($company1->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'api');

        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(200);
        // Admin should only see their own company
        $this->assertCount(1, $response->json());
        $this->assertEquals($company1->id, $response->json()[0]['id']);
    }

    public function test_unauthenticated_returns_401()
    {
        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(401);
    }

    public function test_non_admin_user_returns_403()
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $this->actingAs($regularUser, 'api');

        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(403);
    }
}
