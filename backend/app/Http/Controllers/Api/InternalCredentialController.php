<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;

class InternalCredentialController extends Controller
{
    /**
     * GET /api/internal/credentials
     * Llamado exclusivamente por zia-agent para refrescar sus credenciales de
     * proveedores de IA / Langfuse sin necesitar reiniciar el contenedor cuando
     * un superadmin las cambia desde la UI. Protegido por X-Internal-Secret.
     *
     * ThingsBoard no se expone aquí — ese servicio corre dentro de Laravel
     * mismo (ThingsBoardService), no en zia-agent.
     */
    public function index()
    {
        return response()->json([
            'mistral_api_key'     => SystemSetting::dbValue('MISTRAL_API_KEY'),
            'anthropic_api_key'   => SystemSetting::dbValue('ANTHROPIC_API_KEY'),
            'langfuse_public_key' => SystemSetting::dbValue('LANGFUSE_PUBLIC_KEY'),
            'langfuse_secret_key' => SystemSetting::dbValue('LANGFUSE_SECRET_KEY'),
        ]);
    }
}
