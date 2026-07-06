<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\DocumentChunk;

class InternalDocumentSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Matches phpunit.xml env: INTERNAL_API_SECRET=test-secret-ci */
    private string $validSecret = 'test-secret-ci';

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
    }

    private function vector(array $nonZeroDims): array
    {
        // Vector disperso de dimensión 4 (suficiente para probar similitud de
        // coseno sin manejar arrays de 1024 posiciones en cada assertion).
        $v = array_fill(0, 4, 0.0);
        foreach ($nonZeroDims as $i => $value) {
            $v[$i] = $value;
        }
        return $v;
    }

    public function test_request_without_secret_returns_403()
    {
        $this->postJson('/api/internal/search-documents', [
            'company_id' => $this->company->id,
            'query'      => 'diesel',
        ])->assertStatus(403);
    }

    public function test_returns_chunks_ranked_by_similarity_to_the_query()
    {
        $document = CompanyDocument::factory()->create(['company_id' => $this->company->id]);

        $exactMatch = DocumentChunk::factory()->create([
            'company_document_id' => $document->id,
            'company_id'          => $this->company->id,
            'content'             => 'Consumo de diésel B10 en la flota vehicular',
            'embedding'           => $this->vector([1.0, 0.0, 0.0, 0.0]),
        ]);
        $unrelated = DocumentChunk::factory()->create([
            'company_document_id' => $document->id,
            'company_id'          => $this->company->id,
            'content'             => 'Política de vacaciones del personal',
            'embedding'           => $this->vector([0.0, 1.0, 0.0, 0.0]),
        ]);

        // La query se embebe con el mismo vector que el chunk de diésel → similitud 1.0
        Http::fake([
            '*/embed' => Http::response(['embeddings' => [[1.0, 0.0, 0.0, 0.0]]], 200),
        ]);

        $response = $this->withHeaders(['X-Internal-Secret' => $this->validSecret])
             ->postJson('/api/internal/search-documents', [
                 'company_id' => $this->company->id,
                 'query'      => '¿Cuánto diésel consumió la flota?',
             ]);

        $response->assertOk();
        $results = $response->json('results');

        $this->assertCount(2, $results);
        $this->assertEquals($exactMatch->content, $results[0]['content']);
        $this->assertEqualsWithDelta(1.0, $results[0]['similarity'], 0.001);
        $this->assertEquals($unrelated->content, $results[1]['content']);
        $this->assertLessThan($results[0]['similarity'], $results[1]['similarity']);
    }

    public function test_only_returns_chunks_from_the_requested_company()
    {
        $otherCompany = Company::factory()->create();
        $otherDocument = CompanyDocument::factory()->create(['company_id' => $otherCompany->id]);
        DocumentChunk::factory()->create([
            'company_document_id' => $otherDocument->id,
            'company_id'          => $otherCompany->id,
            'content'             => 'Datos confidenciales de otra empresa',
        ]);

        Http::fake(['*/embed' => Http::response(['embeddings' => [[1.0, 0.0, 0.0, 0.0]]], 200)]);

        $response = $this->withHeaders(['X-Internal-Secret' => $this->validSecret])
             ->postJson('/api/internal/search-documents', [
                 'company_id' => $this->company->id,
                 'query'      => 'algo',
             ]);

        $response->assertOk();
        $this->assertCount(0, $response->json('results'));
    }

    public function test_returns_empty_results_when_company_has_no_documents()
    {
        $response = $this->withHeaders(['X-Internal-Secret' => $this->validSecret])
             ->postJson('/api/internal/search-documents', [
                 'company_id' => $this->company->id,
                 'query'      => 'algo',
             ]);

        $response->assertOk()->assertJson(['results' => []]);
    }
}
