<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\TelemetryReading;
use Illuminate\Http\Request;

class TelemetryReadingController extends Controller
{
    /**
     * GET /api/external/v1/telemetry-readings
     * Lecturas de telemetría de la empresa dueña de la API key usada — jamás
     * de otra, sin importar qué venga en el request. El scope de empresa sale
     * de $request->attributes->get('api_key'), resuelto por ApiKeyAuth.
     */
    public function index(Request $request)
    {
        $companyId = $request->attributes->get('api_key')->company_id;

        $validated = $request->validate([
            'device_id' => 'nullable|integer|exists:iot_devices,id',
            'metric_name' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $readings = TelemetryReading::query()
            ->whereHas('device', fn ($q) => $q->where('company_id', $companyId))
            ->when($validated['device_id'] ?? null, fn ($q, $deviceId) => $q->where('device_id', $deviceId))
            ->when($validated['metric_name'] ?? null, fn ($q, $metric) => $q->where('metric_name', $metric))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->where('timestamp', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->where('timestamp', '<=', $to))
            ->with('device:id,name,type,unit')
            ->orderByDesc('timestamp')
            ->paginate($validated['per_page'] ?? 50);

        return response()->json($readings);
    }
}
