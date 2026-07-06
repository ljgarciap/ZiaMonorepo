<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AssertsCompanyAccess;
use App\Jobs\ProcessCompanyDocument;
use App\Models\Company;
use App\Models\CompanyDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CompanyDocumentController extends Controller
{
    use AssertsCompanyAccess;

    public function index(Request $request, Company $company): JsonResponse
    {
        if ($error = $this->assertAccess($request, $company)) {
            return $error;
        }

        return response()->json(
            $company->companyDocuments()->with('uploader:id,name')->latest()->get()
        );
    }

    public function store(Request $request, Company $company): JsonResponse
    {
        if ($error = $this->assertAccess($request, $company)) {
            return $error;
        }

        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,txt,md',
        ]);

        $file = $request->file('file');
        $path = $file->store("company_documents/company_{$company->id}", 'local');

        $document = $company->companyDocuments()->create([
            'uploaded_by' => auth()->id(),
            'title'       => $file->getClientOriginalName(),
            'file_path'   => $path,
            'mime_type'   => $file->getMimeType(),
            'status'      => 'pending',
        ]);

        ProcessCompanyDocument::dispatch($document);

        return response()->json($document, 201);
    }

    public function destroy(Request $request, Company $company, CompanyDocument $document): JsonResponse
    {
        if ($error = $this->assertAccess($request, $company)) {
            return $error;
        }

        abort_unless($document->company_id === $company->id, 404);

        Storage::disk('local')->delete($document->file_path);
        $document->delete(); // chunks se borran en cascada a nivel de BD

        return response()->json(null, 204);
    }

    private function assertAccess(Request $request, Company $company): ?JsonResponse
    {
        $user = auth()->user();
        $activeRole = $request->header('X-Context-Role') ?: $user->role;

        return $this->assertCompanyPeriodAccess($user, $activeRole, $company);
    }
}
