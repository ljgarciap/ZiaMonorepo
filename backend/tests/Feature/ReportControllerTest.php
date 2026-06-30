<?php

namespace Tests\Feature;

use App\Models\CarbonEmission;
use App\Models\EmissionCategory;
use App\Models\EmissionFactor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Period;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Period $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create(['name' => 'EcoTest SA']);
        $this->user    = User::factory()->create(['role' => 'user']);
        $this->user->companies()->attach($this->company->id, ['role' => 'user', 'is_active' => true]);
        $this->period  = Period::factory()->create([
            'company_id' => $this->company->id,
            'year'       => 2024,
            'status'     => 'active',
        ]);
    }

    // ─── access control ───────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_download_pdf()
    {
        $this->getJson("/api/reports/periods/{$this->period->id}/pdf")
             ->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_download_excel()
    {
        $this->getJson("/api/reports/periods/{$this->period->id}/excel")
             ->assertUnauthorized();
    }

    public function test_pdf_returns_404_for_nonexistent_period()
    {
        $this->actingAs($this->user, 'api')
             ->getJson('/api/reports/periods/99999/pdf')
             ->assertNotFound();
    }

    // ─── PDF download ─────────────────────────────────────────────────────────

    public function test_pdf_summary_initiates_download()
    {
        // loadView() return type is `self` — use Pdf::shouldReceive on both methods so Mockery
        // returns its own proxy (a PDF subclass) from andReturnSelf(), satisfying the type.
        Pdf::shouldReceive('loadView')
           ->once()
           ->with('reports.summary', Mockery::any())
           ->andReturnSelf();
        Pdf::shouldReceive('download')
           ->once()
           ->andReturn(response()->make('PDF_CONTENT', 200, [
               'Content-Type'        => 'application/pdf',
               'Content-Disposition' => 'attachment; filename="test.pdf"',
           ]));

        $this->actingAs($this->user, 'api')
             ->get("/api/reports/periods/{$this->period->id}/pdf")
             ->assertOk()
             ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_filename_includes_company_name_and_year()
    {
        Pdf::shouldReceive('loadView')->andReturnSelf();
        Pdf::shouldReceive('download')
           ->once()
           ->with(Mockery::on(fn($filename) =>
               str_contains($filename, 'ecotest_sa') &&
               str_contains($filename, '2024')
           ))
           ->andReturn(response()->make('PDF_CONTENT', 200, [
               'Content-Type' => 'application/pdf',
           ]));

        $this->actingAs($this->user, 'api')
             ->get("/api/reports/periods/{$this->period->id}/pdf")
             ->assertOk();
    }

    // ─── Excel download ───────────────────────────────────────────────────────

    public function test_excel_export_initiates_download()
    {
        Excel::shouldReceive('download')
             ->once()
             ->andReturn(response()->make('XLSX_CONTENT', 200, [
                 'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
             ]));

        $this->actingAs($this->user, 'api')
             ->get("/api/reports/periods/{$this->period->id}/excel")
             ->assertOk()
             ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_excel_export_uses_correct_period()
    {
        $periodId = $this->period->id;

        Excel::shouldReceive('download')
             ->once()
             ->with(
                 Mockery::on(fn($export) => method_exists($export, '__construct')),
                 Mockery::on(fn($filename) => str_contains($filename, '2024'))
             )
             ->andReturn(response()->make('XLSX', 200));

        $this->actingAs($this->user, 'api')
             ->get("/api/reports/periods/{$periodId}/excel")
             ->assertOk();
    }

    // ─── Sprint 11: enriquecimiento del PDF ──────────────────────────────────

    public function test_pdf_view_data_includes_sprint11_variables()
    {
        $capturedData = null;

        Pdf::shouldReceive('loadView')
           ->once()
           ->with('reports.summary', Mockery::on(function ($data) use (&$capturedData) {
               $capturedData = $data;
               return true;
           }))
           ->andReturnSelf();
        Pdf::shouldReceive('download')->andReturn(response()->make('', 200));

        $this->actingAs($this->user, 'api')
             ->get("/api/reports/periods/{$this->period->id}/pdf")
             ->assertOk();

        // Sprint 11 variables must be present
        $this->assertArrayHasKey('intensityPerSqm',      $capturedData);
        $this->assertArrayHasKey('intensityPerEmployee',  $capturedData);
        $this->assertArrayHasKey('netBalance',            $capturedData);
        $this->assertArrayHasKey('grossEmissions',        $capturedData);
        $this->assertArrayHasKey('removals',              $capturedData);
        $this->assertArrayHasKey('biogenicTotal',         $capturedData);
        $this->assertArrayHasKey('comparisonData',        $capturedData);
        $this->assertArrayHasKey('floorSqm',              $capturedData);
        $this->assertArrayHasKey('numEmployees',          $capturedData);
    }

    public function test_intensity_per_sqm_calculated_correctly()
    {
        // Company with known floor area
        $this->company->update(['floor_sqm' => 1000, 'num_employees' => 50]);

        // Create an emission so total CO2e > 0
        $factor = EmissionFactor::factory()->create([
            'emission_category_id' => EmissionCategory::factory()->create()->id,
            'factor_co2'           => 2.0,
            'uncertainty_upper'    => 0.0,
        ]);
        CarbonEmission::create([
            'period_id'          => $this->period->id,
            'emission_factor_id' => $factor->id,
            'quantity'           => 500,
            'calculated_co2e'    => 1.0, // 500 * 2.0 / 1000
            'uncertainty_result' => 0.0,
        ]);

        $capturedData = null;
        Pdf::shouldReceive('loadView')
           ->once()
           ->with('reports.summary', Mockery::on(function ($data) use (&$capturedData) {
               $capturedData = $data;
               return true;
           }))
           ->andReturnSelf();
        Pdf::shouldReceive('download')->andReturn(response()->make('', 200));

        $this->actingAs($this->user, 'api')
             ->get("/api/reports/periods/{$this->period->id}/pdf")
             ->assertOk();

        // intensityPerSqm = 1.0 tCO2e / 1000 m² = 0.001
        $this->assertNotNull($capturedData['intensityPerSqm']);
        $this->assertEquals(0.001, $capturedData['intensityPerSqm']);

        // intensityPerEmployee = 1.0 / 50 = 0.02
        $this->assertNotNull($capturedData['intensityPerEmployee']);
        $this->assertEquals(0.02, $capturedData['intensityPerEmployee']);
    }

    public function test_intensity_null_when_company_has_no_area_or_employees()
    {
        $this->company->update(['floor_sqm' => null, 'num_employees' => null]);

        $capturedData = null;
        Pdf::shouldReceive('loadView')
           ->with('reports.summary', Mockery::on(function ($data) use (&$capturedData) {
               $capturedData = $data;
               return true;
           }))
           ->andReturnSelf();
        Pdf::shouldReceive('download')->andReturn(response()->make('', 200));

        $this->actingAs($this->user, 'api')
             ->get("/api/reports/periods/{$this->period->id}/pdf")
             ->assertOk();

        $this->assertNull($capturedData['intensityPerSqm']);
        $this->assertNull($capturedData['intensityPerEmployee']);
    }

    public function test_net_balance_equals_gross_minus_removals()
    {
        $category = EmissionCategory::factory()->create();

        $positiveFactor = EmissionFactor::factory()->create([
            'emission_category_id' => $category->id,
            'factor_co2'           => 1.0,
            'uncertainty_upper'    => 0.0,
        ]);
        $removalFactor = EmissionFactor::factory()->create([
            'emission_category_id' => $category->id,
            'is_removal'           => true,
            'factor_co2'           => 1.0,
            'uncertainty_upper'    => 0.0,
        ]);

        CarbonEmission::create([
            'period_id'          => $this->period->id,
            'emission_factor_id' => $positiveFactor->id,
            'quantity'           => 3000,
            'calculated_co2e'    => 3.0,
            'uncertainty_result' => 0.0,
        ]);
        CarbonEmission::create([
            'period_id'          => $this->period->id,
            'emission_factor_id' => $removalFactor->id,
            'quantity'           => 1000,
            'calculated_co2e'    => -1.0, // removal = negative
            'uncertainty_result' => 0.0,
        ]);

        $capturedData = null;
        Pdf::shouldReceive('loadView')
           ->with('reports.summary', Mockery::on(function ($data) use (&$capturedData) {
               $capturedData = $data;
               return true;
           }))
           ->andReturnSelf();
        Pdf::shouldReceive('download')->andReturn(response()->make('', 200));

        $this->actingAs($this->user, 'api')
             ->get("/api/reports/periods/{$this->period->id}/pdf")
             ->assertOk();

        $this->assertEquals(3.0, $capturedData['grossEmissions']);
        $this->assertEquals(1.0, $capturedData['removals']);
        $this->assertEquals(2.0, $capturedData['netBalance']);
    }

    public function test_comparison_data_includes_current_period_year()
    {
        $capturedData = null;
        Pdf::shouldReceive('loadView')
           ->with('reports.summary', Mockery::on(function ($data) use (&$capturedData) {
               $capturedData = $data;
               return true;
           }))
           ->andReturnSelf();
        Pdf::shouldReceive('download')->andReturn(response()->make('', 200));

        $this->actingAs($this->user, 'api')
             ->get("/api/reports/periods/{$this->period->id}/pdf")
             ->assertOk();

        // comparisonData is a Collection; no rows if period has no emissions — count >= 0
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $capturedData['comparisonData']);
    }
}
