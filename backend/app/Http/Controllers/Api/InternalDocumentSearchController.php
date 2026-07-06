<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentSearchService;
use Illuminate\Http\Request;

class InternalDocumentSearchController extends Controller
{
    public function __construct(private DocumentSearchService $searchService) {}

    /**
     * POST /api/internal/search-documents
     * Llamado exclusivamente por zia-agent (tool search_company_documents),
     * vía red interna de Docker. Protegido por X-Internal-Secret.
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'query'      => 'required|string|max:1000',
            'limit'      => 'sometimes|integer|min:1|max:20',
        ]);

        $results = $this->searchService->search(
            $validated['company_id'],
            $validated['query'],
            $validated['limit'] ?? 5,
        );

        return response()->json(['results' => $results]);
    }
}
