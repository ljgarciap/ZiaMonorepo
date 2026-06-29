<?php

namespace App\Services;

use App\Models\CarbonEmission;
use App\Models\Period;
use App\Models\TelemetryReading;
use Illuminate\Support\Facades\Log;

class IoTCarbonIngestionService
{
    /**
     * Convert a telemetry reading into a CarbonEmission record.
     *
     * The operation is idempotent: it recomputes the total quantity from ALL
     * readings stored for this device within the active period's year, so
     * running the cron multiple times never double-counts.
     *
     * Returns null when the device is not fully configured (missing company_id
     * or emission_factor_id) or when no open period exists.
     */
    public function ingestReading(TelemetryReading $reading): ?CarbonEmission
    {
        $device = $reading->device()->with(['company', 'emissionFactor'])->first();

        if (!$device?->company_id || !$device?->emission_factor_id) {
            return null;
        }

        $period = Period::where('company_id', $device->company_id)
            ->whereIn('status', ['open', 'active'])
            ->orderByDesc('year')
            ->first();

        if (!$period) {
            Log::info("[IoT] Sin período activo para empresa {$device->company_id} — lectura omitida.");
            return null;
        }

        $factor = $device->emissionFactor;
        if (!$factor || !$factor->factor_total_co2e) {
            Log::warning("[IoT] Factor de emisión sin tasa CO2e para dispositivo {$device->name} — lectura omitida.");
            return null;
        }

        // Sum ALL readings for this device in the period's year (idempotent across cron runs)
        $totalQuantity = TelemetryReading::where('device_id', $device->id)
            ->whereYear('timestamp', $period->year)
            ->sum('value');

        $calculatedCo2e = round($totalQuantity * $factor->factor_total_co2e, 6);

        return CarbonEmission::updateOrCreate(
            [
                'period_id'          => $period->id,
                'emission_factor_id' => $device->emission_factor_id,
            ],
            [
                'quantity'        => round($totalQuantity, 4),
                'calculated_co2e' => $calculatedCo2e,
                'notes'           => "Auto-ingested from IoT: {$device->name}",
            ]
        );
    }
}
