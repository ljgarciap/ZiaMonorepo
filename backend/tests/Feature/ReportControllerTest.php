<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Period;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;

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
}
