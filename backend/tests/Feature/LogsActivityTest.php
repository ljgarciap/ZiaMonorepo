<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogsActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_a_model_logs_old_and_new_values(): void
    {
        $admin  = User::factory()->create(['role' => 'superadmin']);
        $sector = CompanySector::create(['name' => 'Servicios Test']);
        $company = Company::factory()->create([
            'company_sector_id' => $sector->id,
            'name' => 'Nombre Original',
        ]);

        $this->actingAs($admin, 'api');

        $company->update(['name' => 'Nombre Nuevo']);

        $log = ActivityLog::where('model', Company::class)
            ->where('model_id', $company->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Nombre Original', $log->details['old']['name']);
        $this->assertSame('Nombre Nuevo', $log->details['new']['name']);
    }
}
