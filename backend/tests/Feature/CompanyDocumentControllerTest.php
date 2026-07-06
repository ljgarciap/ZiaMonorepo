<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\DocumentChunk;

class CompanyDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // El job de ingesta llama al /embed del agente — se stubea para no depender de
        // red real. Devuelve un vector por cada texto recibido (el job valida que
        // count(embeddings) === count(chunks), así que el stub debe respetar eso).
        Http::fake(function ($request) {
            $texts = $request->data()['texts'] ?? [];
            return Http::response([
                'embeddings' => array_map(fn () => array_fill(0, 1024, 0.01), $texts),
            ], 200);
        });

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->companies()->attach($this->company->id, ['role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_can_upload_a_document_for_their_company()
    {
        $file = UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf');

        $response = $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$this->company->id}/documents", ['file' => $file]);

        $response->assertCreated()
                 ->assertJsonPath('title', 'factura.pdf');

        $this->assertDatabaseHas('company_documents', [
            'company_id'  => $this->company->id,
            'uploaded_by' => $this->admin->id,
            'title'       => 'factura.pdf',
        ]);
    }

    public function test_upload_rejects_unsupported_file_types()
    {
        $file = UploadedFile::fake()->create('imagen.png', 50, 'image/png');

        $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$this->company->id}/documents", ['file' => $file])
             ->assertStatus(422);
    }

    public function test_admin_from_another_company_cannot_upload()
    {
        $otherCompany = Company::factory()->create();
        $file = UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf');

        $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$otherCompany->id}/documents", ['file' => $file])
             ->assertStatus(403);
    }

    public function test_superadmin_can_upload_for_any_company()
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $file = UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf');

        $this->actingAs($superadmin, 'api')
             ->postJson("/api/admin/companies/{$this->company->id}/documents", ['file' => $file])
             ->assertCreated();
    }

    public function test_index_lists_documents_for_the_company()
    {
        CompanyDocument::factory()->count(2)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->admin, 'api')
             ->getJson("/api/admin/companies/{$this->company->id}/documents");

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_destroy_deletes_the_document_and_its_chunks()
    {
        $document = CompanyDocument::factory()->create(['company_id' => $this->company->id]);
        DocumentChunk::factory()->count(3)->create([
            'company_document_id' => $document->id,
            'company_id'          => $this->company->id,
        ]);

        $this->actingAs($this->admin, 'api')
             ->deleteJson("/api/admin/companies/{$this->company->id}/documents/{$document->id}")
             ->assertNoContent();

        $this->assertDatabaseMissing('company_documents', ['id' => $document->id]);
        $this->assertDatabaseCount('document_chunks', 0);
    }

    public function test_unauthenticated_request_returns_401()
    {
        $this->getJson("/api/admin/companies/{$this->company->id}/documents")
             ->assertStatus(401);
    }

    public function test_uploading_a_text_file_processes_it_into_chunks_with_embeddings()
    {
        // A diferencia de un PDF fake (bytes sin estructura PDF válida, que el
        // parser rechazaría), un .txt con contenido real ejercita el pipeline
        // completo: extracción → chunking → /embed → guardado de chunks.
        $content = str_repeat('Consumo de diésel B10 en la flota vehicular. ', 60); // > 800 chars
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('reporte.txt', $content);

        $response = $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$this->company->id}/documents", ['file' => $file]);

        $response->assertCreated();
        $documentId = $response->json('id');

        $document = CompanyDocument::find($documentId);
        $this->assertEquals('processed', $document->status);
        $this->assertNull($document->error_message);

        $chunks = DocumentChunk::where('company_document_id', $documentId)->get();
        $this->assertGreaterThan(1, $chunks->count());
        foreach ($chunks as $chunk) {
            $this->assertEquals($this->company->id, $chunk->company_id);
            $this->assertCount(1024, $chunk->embedding);
            $this->assertStringContainsString('diésel', $chunk->content);
        }
    }

    public function test_unparseable_document_is_marked_failed_with_an_error_message()
    {
        // Un PDF "fake" de Laravel es solo un archivo del tamaño pedido sin
        // estructura PDF real — el parser debe rechazarlo, y el job debe
        // marcar el documento como failed en vez de dejarlo colgado.
        $file = UploadedFile::fake()->create('corrupto.pdf', 20, 'application/pdf');

        $response = $this->actingAs($this->admin, 'api')
             ->postJson("/api/admin/companies/{$this->company->id}/documents", ['file' => $file]);

        $document = CompanyDocument::find($response->json('id'));

        $this->assertEquals('failed', $document->status);
        $this->assertNotNull($document->error_message);
        $this->assertDatabaseCount('document_chunks', 0);
    }
}
