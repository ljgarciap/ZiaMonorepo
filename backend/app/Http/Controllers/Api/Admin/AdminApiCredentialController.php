<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class AdminApiCredentialController extends Controller
{
    /**
     * Descripciones para la UI — puramente informativas, no afectan la
     * validación (esa se basa en SystemSetting::MANAGED_KEYS).
     */
    private const DESCRIPTIONS = [
        'MISTRAL_API_KEY'      => 'Proveedor de IA primario del Asistente ZIA (chat, cálculo asistido, RAG).',
        'ANTHROPIC_API_KEY'    => 'Proveedor de IA de respaldo del Asistente ZIA, usado si Mistral falla.',
        'LANGFUSE_PUBLIC_KEY'  => 'Observabilidad del agente de IA (trazas de cada llamada a herramientas).',
        'LANGFUSE_SECRET_KEY'  => 'Observabilidad del agente de IA (trazas de cada llamada a herramientas).',
        'THINGSBOARD_HOST'     => 'URL de la instancia de ThingsBoard para telemetría IoT real (no aplica en modo simulado).',
        'THINGSBOARD_USERNAME' => 'Usuario de la cuenta de servicio de ThingsBoard.',
        'THINGSBOARD_PASSWORD' => 'Contraseña de la cuenta de servicio de ThingsBoard.',
    ];

    public function index()
    {
        $settings = SystemSetting::whereIn('key', SystemSetting::MANAGED_KEYS)
            ->with('updatedBy:id,name')
            ->get()
            ->keyBy('key');

        $result = collect(SystemSetting::MANAGED_KEYS)->map(function (string $key) use ($settings) {
            $setting = $settings->get($key);

            return [
                'key'         => $key,
                'description' => self::DESCRIPTIONS[$key] ?? '',
                'is_set'      => (bool) $setting,
                'masked_value' => SystemSetting::maskedValue($key),
                'updated_at'  => $setting?->updated_at,
                'updated_by'  => $setting?->updatedBy?->name,
            ];
        });

        return response()->json($result->values());
    }

    public function update(Request $request, string $key)
    {
        if (!in_array($key, SystemSetting::MANAGED_KEYS, true)) {
            return response()->json(['error' => 'Key no reconocida.'], 422);
        }

        $validated = $request->validate([
            'value' => 'required|string|max:1000',
        ]);

        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $validated['value'], 'updated_by' => $request->user()->id]
        );

        return response()->json([
            'key'          => $key,
            'is_set'       => true,
            'masked_value' => SystemSetting::maskedValue($key),
        ]);
    }

    public function destroy(string $key)
    {
        if (!in_array($key, SystemSetting::MANAGED_KEYS, true)) {
            return response()->json(['error' => 'Key no reconocida.'], 422);
        }

        SystemSetting::where('key', $key)->delete();

        return response()->json(null, 204);
    }
}
