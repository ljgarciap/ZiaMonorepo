<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use App\Models\Company;
use App\Models\Period;
use App\Models\EmissionFactor;
use App\Models\EmissionCategory;
use App\Models\Scope;
use App\Models\CarbonEmission;

class ReclassifyPreexistingIotCarbonEmissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_PATH = 'database/migrations/2026_07_17_130000_reclassify_preexisting_iot_carbon_emissions.php';

    public function test_migration_reclassifies_preexisting_iot_rows_as_source_iot()
    {
        $scope    = Scope::firstOrCreate(['name' => 'Alcance 2'], ['description' => 'Scope 2']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor   = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);
        $company  = Company::factory()->create();
        $period   = Period::factory()->create(['company_id' => $company->id, 'year' => now()->year]);

        // Simula el estado real de un entorno con datos previos: una fila
        // IoT creada ANTES de que existiera esta migración de reclasificación
        // — RefreshDatabase ya corrió la migración 2026_07_17_120100, que
        // backfillea source='manual' en todo lo existente, así que esta fila
        // arranca (incorrectamente) como 'manual', igual que en un deploy real.
        $iotEmission = CarbonEmission::create([
            'period_id'          => $period->id,
            'emission_factor_id' => $factor->id,
            'source'             => 'manual',
            'quantity'           => 123.45,
            'calculated_co2e'    => 0.0156,
            'notes'              => 'Auto-ingested from IoT: Medidor Test',
        ]);

        $manualEmission = CarbonEmission::create([
            'period_id'          => $period->id,
            'emission_factor_id' => EmissionFactor::factory()->create(['emission_category_id' => $category->id])->id,
            'source'             => 'manual',
            'quantity'           => 50.0,
            'calculated_co2e'    => 0.01,
            'notes'              => 'Cargado a mano por el usuario',
        ]);

        // Re-correr específicamente esta migración (ya se corrió una vez al
        // preparar la BD de test vía RefreshDatabase) para ejercitar su up()
        // contra las filas preexistentes, igual que pasaría en un deploy real.
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);
        Artisan::call('migrate', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertEquals('iot', $iotEmission->fresh()->source);
        // Una emisión manual real (notes no coincide con el patrón IoT) no debe tocarse.
        $this->assertEquals('manual', $manualEmission->fresh()->source);
    }

    public function test_migration_down_reverts_reclassified_rows_to_manual()
    {
        $scope    = Scope::firstOrCreate(['name' => 'Alcance 2'], ['description' => 'Scope 2']);
        $category = EmissionCategory::factory()->create(['scope_id' => $scope->id]);
        $factor   = EmissionFactor::factory()->create(['emission_category_id' => $category->id]);
        $company  = Company::factory()->create();
        $period   = Period::factory()->create(['company_id' => $company->id, 'year' => now()->year]);

        $emission = CarbonEmission::create([
            'period_id'          => $period->id,
            'emission_factor_id' => $factor->id,
            'source'             => 'iot',
            'quantity'           => 10.0,
            'calculated_co2e'    => 0.001,
            'notes'              => 'Auto-ingested from IoT: Medidor Test',
        ]);

        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertEquals('manual', $emission->fresh()->source);
    }
}
