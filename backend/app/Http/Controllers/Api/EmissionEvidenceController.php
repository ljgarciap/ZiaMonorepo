<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbonEmission;
use App\Models\EmissionEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmissionEvidenceController extends Controller
{
    public function index(CarbonEmission $emission)
    {
        return response()->json(
            $emission->evidences()->with('user:id,name,email')->get()
        );
    }

    public function store(Request $request, CarbonEmission $emission)
    {
        $request->validate([
            'file'        => 'required|file|max:20480|mimes:pdf,xlsx,xls,csv,jpg,jpeg,png,webp',
            'description' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $path = $file->store("evidences/emission_{$emission->id}", 'local');

        $evidence = $emission->evidences()->create([
            'user_id'     => auth()->id(),
            'file_name'   => $file->getClientOriginalName(),
            'file_path'   => $path,
            'file_size'   => $file->getSize(),
            'mime_type'   => $file->getMimeType(),
            'description' => $request->input('description'),
        ]);

        return response()->json($evidence->load('user:id,name,email'), 201);
    }

    public function destroy(CarbonEmission $emission, EmissionEvidence $evidence)
    {
        abort_unless($evidence->carbon_emission_id === $emission->id, 404);

        $user       = auth()->user();
        $activeRole = request()->header('X-Context-Role') ?: $user->role;

        // Owner or admin/superadmin can delete
        if ($evidence->user_id !== $user->id && !in_array($activeRole, ['admin', 'superadmin'])) {
            return response()->json(['error' => 'Sin permiso para eliminar esta evidencia.'], 403);
        }

        Storage::disk('local')->delete($evidence->file_path);
        $evidence->delete();

        return response()->json(null, 204);
    }

    public function download(CarbonEmission $emission, EmissionEvidence $evidence)
    {
        abort_unless($evidence->carbon_emission_id === $emission->id, 404);

        if (!Storage::disk('local')->exists($evidence->file_path)) {
            return response()->json(['error' => 'Archivo no encontrado.'], 404);
        }

        return Storage::disk('local')->download($evidence->file_path, $evidence->file_name);
    }
}
