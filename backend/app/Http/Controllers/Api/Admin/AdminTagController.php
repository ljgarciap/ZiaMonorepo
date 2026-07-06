<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AssertsCompanyAccess;
use App\Models\Company;
use App\Models\Tag;
use Illuminate\Http\Request;

class AdminTagController extends Controller
{
    use AssertsCompanyAccess;


    /**
     * GET /admin/tags — catálogo global (Superadmin).
     */
    public function index()
    {
        return response()->json(Tag::with('sector:id,name')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'company_sector_id' => 'nullable|exists:company_sectors,id',
        ]);

        $tag = Tag::create($data + ['is_active' => true]);

        return response()->json($tag->load('sector:id,name'), 201);
    }

    public function update(Request $request, Tag $tag)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'company_sector_id' => 'nullable|exists:company_sectors,id',
        ]);

        $tag->update($data);

        return response()->json($tag->load('sector:id,name'));
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();
        return response()->json(null, 204);
    }

    public function toggle(Tag $tag)
    {
        $tag->update(['is_active' => !$tag->is_active]);
        return response()->json($tag);
    }

    /**
     * GET /companies/{company}/available-tags
     * Grupo de tags preconfigurados que el Admin puede asignar a su empresa:
     * los globales (sin sector) + los específicos del sector de la empresa.
     */
    public function availableForCompany(Request $request, Company $company)
    {
        $user = $request->user();
        $activeRole = $request->header('X-Context-Role') ?: $user->role;
        if ($error = $this->assertCompanyPeriodAccess($user, $activeRole, $company)) {
            return $error;
        }

        $tags = Tag::where('is_active', true)
            ->where(function ($query) use ($company) {
                $query->whereNull('company_sector_id')
                    ->orWhere('company_sector_id', $company->company_sector_id);
            })
            ->orderBy('name')
            ->get();

        return response()->json($tags);
    }
}
