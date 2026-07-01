<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\IotDevice;
use App\Models\TelemetryReading;
use App\Models\TelemetryAlert;
use Illuminate\Support\Facades\DB;

class AdminIotController extends Controller
{
    // SA-12: listado de dispositivos con estado operativo para el superadmin
    public function index()
    {
        $devices = IotDevice::with('company:id,name')
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($device) {
                $lastReading = TelemetryReading::where('device_id', $device->id)
                    ->orderByDesc('timestamp')
                    ->first();

                $pendingAlerts = TelemetryAlert::where('device_id', $device->id)
                    ->where('resolved', false)
                    ->count();

                $totalReadings = TelemetryReading::where('device_id', $device->id)->count();

                return [
                    'id'              => $device->id,
                    'name'            => $device->name,
                    'type'            => $device->type,
                    'location'        => $device->location,
                    'company_id'      => $device->company_id,
                    'company_name'    => $device->company?->name ?? '—',
                    'last_seen'       => $lastReading?->timestamp,
                    'last_metric'     => $lastReading?->metric_name,
                    'last_value'      => $lastReading ? round((float) $lastReading->value, 2) : null,
                    'pending_alerts'  => $pendingAlerts,
                    'total_readings'  => $totalReadings,
                    'status'          => $this->computeStatus($lastReading?->timestamp, $pendingAlerts),
                ];
            });

        // Resumen por empresa
        $summary = $devices->groupBy('company_id')->map(fn ($devs) => [
            'company_name'   => $devs->first()['company_name'],
            'device_count'   => $devs->count(),
            'alert_count'    => $devs->sum('pending_alerts'),
        ])->values();

        return response()->json([
            'devices' => $devices,
            'summary' => $summary,
            'totals'  => [
                'devices'        => $devices->count(),
                'pending_alerts' => $devices->sum('pending_alerts'),
                'online'         => $devices->where('status', 'online')->count(),
                'offline'        => $devices->where('status', 'offline')->count(),
                'warning'        => $devices->where('status', 'warning')->count(),
            ],
        ]);
    }

    private function computeStatus(?string $lastSeen, int $pendingAlerts): string
    {
        if ($pendingAlerts > 0) return 'warning';
        if (!$lastSeen) return 'offline';
        $hoursAgo = now()->diffInHours($lastSeen, false);
        return $hoursAgo > -24 ? 'online' : 'offline';
    }
}
