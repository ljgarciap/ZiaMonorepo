<?php

namespace App\Services;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Http;

class DocumentSearchService
{
    /**
     * Embeds the query and returns the top-N most similar chunks for a company,
     * ranked by cosine similarity computed in PHP (see ADR-002 / migration note:
     * embeddings are stored as JSON, not a native pgvector column, to keep the
     * sqlite test suite portable — fine at this data scale).
     *
     * @return array<int, array{document_id:int, document_title:string, content:string, similarity:float}>
     */
    public function search(int $companyId, string $query, int $limit = 5): array
    {
        $chunks = DocumentChunk::where('company_id', $companyId)
            ->with('document:id,title')
            ->get();

        if ($chunks->isEmpty()) {
            return [];
        }

        $agentUrl = rtrim(config('services.zia_agent_url', 'http://zia-agent:8001'), '/');
        $response = Http::timeout(30)->post("{$agentUrl}/embed", ['texts' => [$query]]);

        if (!$response->successful()) {
            throw new \Exception('El servicio de embeddings respondió con error: ' . $response->status());
        }

        $queryVector = $response->json('embeddings.0');
        if (!is_array($queryVector)) {
            throw new \Exception('El servicio de embeddings no devolvió un vector válido.');
        }

        return $chunks
            ->map(fn (DocumentChunk $chunk) => [
                'document_id'    => $chunk->company_document_id,
                'document_title' => $chunk->document?->title ?? 'Documento eliminado',
                'content'        => $chunk->content,
                'similarity'     => $this->cosineSimilarity($queryVector, $chunk->embedding),
            ])
            ->sortByDesc('similarity')
            ->take($limit)
            ->values()
            ->all();
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $other = $b[$i] ?? 0.0;
            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
