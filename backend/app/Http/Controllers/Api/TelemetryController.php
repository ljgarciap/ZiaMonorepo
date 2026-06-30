<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelemetryAlert;
use App\Models\TelemetryReading;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    /**
     * GET /telemetry/live
     * Latest reading per device for the current company, plus unresolved alerts.
     */
    public function live(Request $request)
    {
        $companyId = $request->header('X-Company-ID');

        $latestReadings = TelemetryReading::with('device')
            ->join('iot_devices', 'telemetry_readings.device_id', '=', 'iot_devices.id')
            ->select('telemetry_readings.*')
            ->whereNull('iot_devices.deleted_at')
            ->when($companyId, fn($q) => $q->where('iot_devices.company_id', $companyId))
            ->whereRaw('telemetry_readings.timestamp = (
                SELECT MAX(tr2.timestamp)
                FROM telemetry_readings AS tr2
                WHERE tr2.device_id = telemetry_readings.device_id
            )')
            ->get()
            ->map(fn($r) => [
                'metric_name' => $r->metric_name,
                'value'       => $r->value,
                'timestamp'   => $r->timestamp->toIso8601String(),
                'device_name' => $r->device?->name ?? 'Desconocido',
            ]);

        $alerts = TelemetryAlert::with('device')
            ->where('resolved', false)
            ->when($companyId, fn($q) => $q->whereHas(
                'device',
                fn($d) => $d->where('company_id', $companyId)
            ))
            ->orderByDesc('detected_at')
            ->get();

        return response()->json([
            'readings' => $latestReadings,
            'alerts'   => $alerts,
        ]);
    }

    /**
     * GET /telemetry/history
     * Paginated readings with optional device_id, from, and to filters.
     */
    public function history(Request $request)
    {
        $companyId = $request->header('X-Company-ID');

        $query = TelemetryReading::with('device')
            ->join('iot_devices', 'telemetry_readings.device_id', '=', 'iot_devices.id')
            ->select('telemetry_readings.*')
            ->whereNull('iot_devices.deleted_at')
            ->when($companyId, fn($q) => $q->where('iot_devices.company_id', $companyId))
            ->when($request->device_id, fn($q, $id) => $q->where('telemetry_readings.device_id', $id))
            ->when($request->from, fn($q, $from) => $q->where('telemetry_readings.timestamp', '>=', $from))
            ->when($request->to, fn($q, $to) => $q->where('telemetry_readings.timestamp', '<=', $to))
            ->orderByDesc('telemetry_readings.timestamp');

        return response()->json($query->paginate((int) $request->input('per_page', 20)));
    }
}
