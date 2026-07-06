<?php

namespace App\Jobs;

use App\Models\CompanyDocument;
use App\Models\DocumentChunk;
use App\Services\DocumentTextExtractor;
use App\Services\TextChunker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessCompanyDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public CompanyDocument $document)
    {
    }

    public function handle(DocumentTextExtractor $extractor, TextChunker $chunker): void
    {
        $this->document->update(['status' => 'processing']);

        try {
            $text = $extractor->extract($this->document->file_path, $this->document->mime_type);
            $chunks = $chunker->chunk($text);

            if (empty($chunks)) {
                $this->document->update([
                    'status'        => 'failed',
                    'error_message' => 'El documento no contiene texto extraíble.',
                ]);
                return;
            }

            $agentUrl = rtrim(config('services.zia_agent_url', 'http://zia-agent:8001'), '/');
            $response = Http::timeout(120)->post("{$agentUrl}/embed", ['texts' => $chunks]);

            if (!$response->successful()) {
                throw new \Exception('El servicio de embeddings respondió con error: ' . $response->status());
            }

            $embeddings = $response->json('embeddings');

            if (!is_array($embeddings) || count($embeddings) !== count($chunks)) {
                throw new \Exception('El servicio de embeddings devolvió una cantidad de vectores distinta a la de chunks.');
            }

            foreach ($chunks as $index => $content) {
                DocumentChunk::create([
                    'company_document_id' => $this->document->id,
                    'company_id'           => $this->document->company_id,
                    'chunk_index'          => $index,
                    'content'              => $content,
                    'embedding'            => $embeddings[$index],
                ]);
            }

            $this->document->update(['status' => 'processed', 'error_message' => null]);
        } catch (\Throwable $e) {
            Log::error("[RAG] Falló el procesamiento del documento {$this->document->id}: " . $e->getMessage());
            $this->document->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
