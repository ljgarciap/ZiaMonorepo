<?php

namespace App\Services;

use App\Models\IotDevice;
use App\Models\TelemetryReading;

class BaseloadDeviationService
{
    /**
     * Analyze a reading against the device's configured baseline.
     *
     * @return array{deviation_pct: float, excess_kwh: float, is_off_hours: bool, baseline_kwh: float}|null
     *         null when no baseline is configured or the device type is not 'energy'
     */
    public function analyze(IotDevice $device, TelemetryReading $reading): ?array
    {
        if ($device->type !== 'energy' || $device->baseline_kwh === null || $device->baseline_kwh <= 0) {
            return null;
        }

        $baseline = (float) $device->baseline_kwh;
        $value    = (float) $reading->value;

        $deviationPct = (($value - $baseline) / $baseline) * 100.0;
        $excessKwh    = max(0.0, $value - $baseline);
        $isOffHours   = $this->isOffHours($device, $reading);

        return [
            'deviation_pct' => round($deviationPct, 2),
            'excess_kwh'    => round($excessKwh, 3),
            'is_off_hours'  => $isOffHours,
            'baseline_kwh'  => $baseline,
        ];
    }

    /**
     * Determine whether a reading occurred outside configured office hours.
     */
    public function isOffHours(IotDevice $device, TelemetryReading $reading): bool
    {
        $timestamp = $reading->timestamp;
        $dayOfWeek = (int) $timestamp->format('N'); // 1 Mon – 7 Sun

        if ($dayOfWeek >= 6) {
            return true;
        }

        $start = $device->office_hours_start ?? '08:00:00';
        $end   = $device->office_hours_end   ?? '18:00:00';

        $currentTime = $timestamp->format('H:i:s');

        return $currentTime < $start || $currentTime >= $end;
    }
}
