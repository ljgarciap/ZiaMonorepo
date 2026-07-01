<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionnaireTemplate;
use App\Models\QuestionnaireQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminQuestionnaireController extends Controller
{
    // SA-10: listado de plantillas con conteo de preguntas
    public function index()
    {
        $templates = QuestionnaireTemplate::withCount('questions')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($templates);
    }

    public function show(QuestionnaireTemplate $template)
    {
        return response()->json($template->load('questions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'sector'      => 'nullable|string|max:100',
        ]);

        $template = QuestionnaireTemplate::create(array_merge($validated, [
            'status'  => 'draft',
            'version' => 1,
        ]));

        return response()->json($template, 201);
    }

    public function update(Request $request, QuestionnaireTemplate $template)
    {
        if ($template->status === 'published') {
            return response()->json(['message' => 'No se puede editar una plantilla publicada. Crea una nueva versión.'], 422);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'sector'      => 'nullable|string|max:100',
        ]);

        $template->update($validated);
        return response()->json($template->load('questions'));
    }

    public function destroy(QuestionnaireTemplate $template)
    {
        if ($template->status === 'published') {
            return response()->json(['message' => 'No se puede eliminar una plantilla publicada.'], 422);
        }
        $template->delete();
        return response()->json(null, 204);
    }

    // Publicar plantilla (solo superadmin)
    public function publish(QuestionnaireTemplate $template)
    {
        if ($template->questions()->count() === 0) {
            return response()->json(['message' => 'No se puede publicar sin preguntas.'], 422);
        }

        $template->update([
            'status'      => 'published',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json($template->load('questions'));
    }

    // Archivar plantilla
    public function archive(QuestionnaireTemplate $template)
    {
        $template->update(['status' => 'archived']);
        return response()->json($template);
    }

    // Nueva versión desde plantilla existente
    public function newVersion(QuestionnaireTemplate $template)
    {
        DB::beginTransaction();
        try {
            $newTemplate = $template->replicate(['approved_by', 'approved_at']);
            $newTemplate->status  = 'draft';
            $newTemplate->version = $template->version + 1;
            $newTemplate->save();

            foreach ($template->questions as $q) {
                $newQ = $q->replicate();
                $newQ->template_id = $newTemplate->id;
                $newQ->save();
            }

            DB::commit();
            return response()->json($newTemplate->load('questions'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear nueva versión.'], 500);
        }
    }

    // ── Preguntas ───────────────────────────────────────────────

    public function storeQuestion(Request $request, QuestionnaireTemplate $template)
    {
        if ($template->status === 'published') {
            return response()->json(['message' => 'No se pueden agregar preguntas a una plantilla publicada.'], 422);
        }

        $validated = $request->validate([
            'question_text' => 'required|string|max:1000',
            'question_type' => 'required|string|in:text,number,select,multiselect,boolean,file',
            'options'       => 'nullable|array',
            'unit'          => 'nullable|string|max:30',
            'scope_hint'    => 'nullable|string|in:scope_1,scope_2,scope_3',
            'category_hint' => 'nullable|string|max:255',
            'required'      => 'boolean',
            'help_text'     => 'nullable|string',
            'order'         => 'integer|min:0',
        ]);

        $question = $template->questions()->create($validated);
        return response()->json($question, 201);
    }

    public function updateQuestion(Request $request, QuestionnaireTemplate $template, QuestionnaireQuestion $question)
    {
        if ($template->status === 'published') {
            return response()->json(['message' => 'No se puede editar preguntas en una plantilla publicada.'], 422);
        }

        $validated = $request->validate([
            'question_text' => 'sometimes|string|max:1000',
            'question_type' => 'sometimes|string|in:text,number,select,multiselect,boolean,file',
            'options'       => 'nullable|array',
            'unit'          => 'nullable|string|max:30',
            'scope_hint'    => 'nullable|string|in:scope_1,scope_2,scope_3',
            'category_hint' => 'nullable|string|max:255',
            'required'      => 'boolean',
            'help_text'     => 'nullable|string',
            'order'         => 'integer|min:0',
        ]);

        $question->update($validated);
        return response()->json($question);
    }

    public function destroyQuestion(QuestionnaireTemplate $template, QuestionnaireQuestion $question)
    {
        if ($template->status === 'published') {
            return response()->json(['message' => 'No se pueden eliminar preguntas de una plantilla publicada.'], 422);
        }
        $question->delete();
        return response()->json(null, 204);
    }

    // Reordenar preguntas (array de {id, order})
    public function reorderQuestions(Request $request, QuestionnaireTemplate $template)
    {
        $request->validate(['order' => 'required|array', 'order.*.id' => 'required|integer', 'order.*.order' => 'required|integer']);

        foreach ($request->order as $item) {
            QuestionnaireQuestion::where('id', $item['id'])->where('template_id', $template->id)
                ->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Orden actualizado']);
    }
}
