<?php

namespace App\Services;

use App\Models\TelemetryReading;
use App\Models\TelemetryAlert;
use App\Models\IotDevice;
use Illuminate\Support\Facades\Log;

class TelemetryAlertService
{
    public function __construct(
        private readonly BaseloadDeviationService $deviationService = new BaseloadDeviationService()
    ) {}

    /**
     * Evaluate a new telemetry reading for operational inefficiencies.
     *
     * @param TelemetryReading $reading
     * @return TelemetryAlert|null Triggered alert or null if normal
     */
    public function checkReading(TelemetryReading $reading): ?TelemetryAlert
    {
        $device = $reading->device;
        if (!$device) {
            return null;
        }

        $timestamp = $reading->timestamp;
        $hour = intval($timestamp->format('H'));
        $dayOfWeek = intval($timestamp->format('N')); // 1 (Mon) - 7 (Sun)

        $isWeekend = ($dayOfWeek >= 6);
        $isNight = ($hour >= 20 || $hour < 6);

        // Detect off-hours operational inefficiency
        if ($isWeekend || $isNight) {
            $threshold = 0.0;
            $alertType = 'off_hours_excess';
            $severity = 'warning';
            $triggerAlert = false;
            $message = '';

            if ($device->type === 'energy') {
                $threshold = 25.0; // Max 25 kWh allowed off-hours
                if ($reading->value > $threshold) {
                    $triggerAlert = true;
                    $severity = $reading->value > 40.0 ? 'critical' : 'warning';
                    $message = "Consumo eléctrico ineficiente detectado fuera de horario comercial en {$device->location}. " .
                               "Consumo actual: {$reading->value} kWh, Umbral máximo permitido: {$threshold} kWh.";
                }
            } elseif ($device->type === 'water') {
                $threshold = 0.3; // Max 0.3 m3 allowed off-hours (suggests a leak or open valve)
                if ($reading->value > $threshold) {
                    $triggerAlert = true;
                    $severity = $reading->value > 1.0 ? 'critical' : 'warning';
                    $message = "Consumo inusual de agua detectado en {$device->location} durante horario no laboral. " .
                               "Consumo actual: {$reading->value} m3, Umbral máximo permitido: {$threshold} m3. Posible fuga activa.";
                }
            } elseif ($device->type === 'waste') {
                $threshold = 10.0; // Max 10 kg off-hours (smart scale — unexpected waste generation)
                if ($reading->value > $threshold) {
                    $triggerAlert = true;
                    $severity = $reading->value > 50.0 ? 'critical' : 'warning';
                    $message = "Generación inusual de residuos detectada en {$device->location} fuera de horario. " .
                               "Peso registrado: {$reading->value} kg, Umbral máximo: {$threshold} kg.";
                }
            }

            if ($triggerAlert) {
                Log::warning("ALERTA DE TELEMETRÍA: {$message}");

                return TelemetryAlert::create([
                    'device_id' => $device->id,
                    'alert_type' => $alertType,
                    'severity' => $severity,
                    'message' => $message,
                    'threshold_value' => $threshold,
                    'actual_value' => $reading->value,
                    'detected_at' => $timestamp,
                    'resolved' => false
                ]);
            }
        }

        // Baseload deviation: detect in-hours excess for energy devices with a configured baseline.
        // Fires only when no off-hours alert was already triggered for this reading.
        if ($device->type === 'energy') {
            $deviation = $this->deviationService->analyze($device, $reading);
            if ($deviation !== null && $deviation['deviation_pct'] > 20.0 && !$deviation['is_off_hours']) {
                $deviationPct = $deviation['deviation_pct'];
                $excessKwh    = $deviation['excess_kwh'];
                $baseline     = $deviation['baseline_kwh'];
                $severity     = $deviationPct > 50.0 ? 'critical' : 'warning';
                $message      = "Consumo eléctrico {$deviationPct}% por encima del baseline ({$baseline} kWh) "
                              . "en {$device->location}. Exceso: {$excessKwh} kWh.";

                Log::warning("ALERTA BASELOAD: {$message}");

                return TelemetryAlert::create([
                    'device_id'       => $device->id,
                    'alert_type'      => 'baseload_excess',
                    'severity'        => $severity,
                    'message'         => $message,
                    'threshold_value' => $baseline,
                    'actual_value'    => (float) $reading->value,
                    'detected_at'     => $timestamp,
                    'resolved'        => false,
                ]);
            }
        }

        return null;
    }
}
