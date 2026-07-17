<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ThingsBoardService
{
    protected $host;
    protected $username;
    protected $password;
    protected $mockMode;

    public function __construct()
    {
        // Las credenciales se resuelven perezosamente (ver host()/username()/
        // password() abajo), no acá — este servicio se inyecta en el
        // constructor de SyncTelemetryCommand, y Laravel puede resolver ese
        // comando (para construir el listado de artisan) durante el
        // bootstrap de CUALQUIER comando, incluyendo `migrate` sobre una BD
        // recién creada donde `system_settings` todavía no existe. Consultar
        // la tabla acá rompería ese bootstrap; consultarla solo al hacer una
        // llamada real a ThingsBoard no.
        // THINGSBOARD_MOCK es una bandera de entorno, no una credencial — se
        // queda en .env, no es gestionable desde la UI de API Keys.
        $this->mockMode = filter_var(env('THINGSBOARD_MOCK', true), FILTER_VALIDATE_BOOLEAN);
    }

    protected function host(): string
    {
        return $this->host ??= SystemSetting::resolve('THINGSBOARD_HOST', 'https://thingsboard.cloud');
    }

    protected function username(): ?string
    {
        return $this->username ??= SystemSetting::resolve('THINGSBOARD_USERNAME');
    }

    protected function password(): ?string
    {
        return $this->password ??= SystemSetting::resolve('THINGSBOARD_PASSWORD');
    }

    /**
     * Get the latest telemetry value for a device and key.
     *
     * @param string $deviceId ThingsBoard UUID of the device
     * @param string $metricName Metric name to retrieve
     * @return array{value: float, timestamp: string, is_fallback: bool} `is_fallback`
     *   is true only when a REAL API call failed and this is a simulated
     *   substitute (not when THINGSBOARD_MOCK=true is simply doing its job).
     *   Callers that persist a value as a running baseline (e.g. a cumulative
     *   meter's last-known reading) must skip the cycle instead of trusting a
     *   fallback value — an intentional mock reading and an emergency
     *   substitute for a real one are not the same thing.
     */
    public function getLatestTelemetry(string $deviceId, string $metricName): array
    {
        if ($this->mockMode) {
            return $this->generateMockTelemetry($deviceId, $metricName) + ['is_fallback' => false];
        }

        try {
            $token = $this->getAuthToken();
            if (!$token) {
                Log::error('ThingsBoard auth failed. Falling back to mock data.');
                return $this->generateMockTelemetry($deviceId, $metricName) + ['is_fallback' => true];
            }

            // Get latest values from ThingsBoard API
            $response = Http::withHeaders([
                'X-Authorization' => 'Bearer ' . $token
            ])->get("{$this->host()}/api/plugins/telemetry/DEVICE/{$deviceId}/values/timeseries", [
                'keys' => $metricName
            ]);

            if ($response->successful() && isset($response->json()[$metricName][0])) {
                $data = $response->json()[$metricName][0];
                return [
                    'value' => floatval($data['value']),
                    // ThingsBoard timestamps are in milliseconds
                    'timestamp' => now()->setTimestamp(intval($data['ts'] / 1000))->toDateTimeString(),
                    'is_fallback' => false,
                ];
            }

            Log::warning("No telemetry found for device {$deviceId} and key {$metricName} in ThingsBoard. Using simulated fallback.");
            return $this->generateMockTelemetry($deviceId, $metricName) + ['is_fallback' => true];
        } catch (\Exception $e) {
            Log::error("ThingsBoard API error: " . $e->getMessage() . ". Using simulated fallback.");
            return $this->generateMockTelemetry($deviceId, $metricName) + ['is_fallback' => true];
        }
    }

    /**
     * Get every telemetry point in [startTs, endTs] (milliseconds), not just the
     * latest one. Needed for sensors that report by discrete event (e.g. a scale
     * that publishes once per weighing and resets to 0) — polling only "latest"
     * can miss events that already reset before the next poll.
     *
     * Unlike getLatestTelemetry(), this does NOT fall back to mock data on a real
     * API failure — and it distinguishes "confirmed zero events" from "the
     * request failed" by returning null on failure. That distinction matters to
     * the caller: it should only advance its sync watermark (last_synced_at) on
     * a confirmed result, never on a failure, or a real event that happened
     * during an outage would be permanently skipped instead of retried next run.
     *
     * @return array<int, array{value: float, timestamp: string}>|null null on failure
     */
    public function getTimeseriesRange(string $deviceId, string $metricName, int $startTsMs, int $endTsMs): ?array
    {
        if ($this->mockMode) {
            return $this->generateMockTimeseriesRange($deviceId, $metricName, $startTsMs, $endTsMs);
        }

        try {
            $token = $this->getAuthToken();
            if (!$token) {
                Log::error('ThingsBoard auth failed while fetching range. Sync watermark will not advance.');
                return null;
            }

            $response = Http::withHeaders([
                'X-Authorization' => 'Bearer ' . $token
            ])->get("{$this->host()}/api/plugins/telemetry/DEVICE/{$deviceId}/values/timeseries", [
                'keys' => $metricName,
                'startTs' => $startTsMs,
                'endTs' => $endTsMs,
                'limit' => 1000,
            ]);

            if (!$response->successful()) {
                Log::error("ThingsBoard API error (range): HTTP {$response->status()}. Sync watermark will not advance.");
                return null;
            }

            if (!isset($response->json()[$metricName])) {
                return []; // Respuesta exitosa, sin eventos en el rango — resultado confirmado.
            }

            return collect($response->json()[$metricName])
                ->map(fn (array $point) => [
                    'value' => floatval($point['value']),
                    'timestamp' => now()->setTimestamp(intval($point['ts'] / 1000))->toDateTimeString(),
                ])
                ->all();
        } catch (\Exception $e) {
            Log::error("ThingsBoard API error (range): " . $e->getMessage() . ". Sync watermark will not advance.");
            return null;
        }
    }

    /**
     * Fetch or retrieve cached JWT Auth token from ThingsBoard.
     */
    protected function getAuthToken(): ?string
    {
        return Cache::remember('thingsboard_jwt_token', 55, function () {
            try {
                $response = Http::post("{$this->host()}/api/auth/login", [
                    'username' => $this->username(),
                    'password' => $this->password()
                ]);

                if ($response->successful()) {
                    return $response->json()['token'] ?? null;
                }
            } catch (\Exception $e) {
                Log::error("ThingsBoard Token Fetch failed: " . $e->getMessage());
            }
            return null;
        });
    }

    /**
     * Generate realistic simulated telemetry data based on time of day.
     */
    protected function generateMockTelemetry(string $deviceId, string $metricName): array
    {
        // energy_active_import_wh es un contador acumulado en la vida real —
        // SyncTelemetryCommand calcula el delta entre dos lecturas de esta key.
        // Devolver un valor aleatorio independiente en cada llamada (como el
        // resto de este mock) rompería esa cuenta: dos valores sin relación
        // entre sí casi siempre dan un delta negativo, recortado a 0. Se
        // simula acá como un contador real: crece con cada llamada.
        if (str_contains($metricName, 'energy_active_import')) {
            return $this->generateMockCumulativeCounter($deviceId, $metricName);
        }

        $hour = intval(now()->format('H'));
        $dayOfWeek = intval(now()->format('N')); // 1 (Mon) - 7 (Sun)
        $isWorkingHours = ($hour >= 8 && $hour < 18) && ($dayOfWeek <= 5);

        // Water metric
        if (str_contains($metricName, 'water') || str_contains($deviceId, 'water')) {
            if ($isWorkingHours) {
                // Higher consumption during office hours: 0.8 to 2.2 m3 per interval
                $value = rand(80, 220) / 100.0;
            } else {
                // Lower consumption during off-hours: 0.01 to 0.15 m3
                // Proactively trigger a leak/excess 5% of the time to test alerts!
                if (rand(1, 100) > 95) {
                    $value = rand(150, 250) / 100.0; // Simulated off-hours leak
                    Log::warning("Simulated water leak event triggered for device {$deviceId}.");
                } else {
                    $value = rand(1, 15) / 100.0;
                }
            }
        } 
        // Energy metric (default)
        else {
            if ($isWorkingHours) {
                // Higher electricity draw during work hours: 45 to 85 kWh
                $value = rand(450, 850) / 10.0;
            } else {
                // Lower off-hours draw: 5 to 15 kWh
                // Proactively trigger high night-time consumption 5% of the time (AC/lights left on)
                if (rand(1, 100) > 95) {
                    $value = rand(350, 500) / 10.0; // Simulated inefficency
                    Log::warning("Simulated off-hours high electricity usage event triggered for device {$deviceId}.");
                } else {
                    $value = rand(50, 150) / 10.0;
                }
            }
        }

        return [
            'value' => $value,
            'timestamp' => now()->toDateTimeString()
        ];
    }

    /**
     * Simulate a real cumulative meter (e.g. energy_active_import_wh): each
     * call adds a realistic increment on top of the last value, persisted in
     * cache per device+key. Never resets on its own — mirrors a real meter,
     * which only grows.
     */
    protected function generateMockCumulativeCounter(string $deviceId, string $metricName): array
    {
        $hour = intval(now()->format('H'));
        $dayOfWeek = intval(now()->format('N'));
        $isWorkingHours = ($hour >= 8 && $hour < 18) && ($dayOfWeek <= 5);

        // Incremento de este intervalo, en Wh — mismo orden de magnitud que
        // el mock de energía por intervalo (4.5-8.5 kWh en horario laboral,
        // 0.5-1.5 kWh fuera de horario), convertido a Wh.
        $incrementWh = $isWorkingHours ? rand(4500, 8500) : rand(500, 1500);

        $cacheKey = "thingsboard_mock_cumulative_{$deviceId}_{$metricName}";
        $current = Cache::get($cacheKey, 0) + $incrementWh;
        Cache::forever($cacheKey, $current);

        return [
            'value' => $current,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    /**
     * Simulate a scale that only reports when someone weighs something: 0 to 3
     * random weighing events spread across the requested range.
     */
    protected function generateMockTimeseriesRange(string $deviceId, string $metricName, int $startTsMs, int $endTsMs): array
    {
        $events = [];
        $eventCount = rand(0, 3);
        $endTsMs = max($startTsMs, $endTsMs);

        for ($i = 0; $i < $eventCount; $i++) {
            $ts = rand($startTsMs, $endTsMs);
            $events[] = [
                'value' => rand(5, 400) / 10.0, // 0.5 a 40 kg de papel por evento
                'timestamp' => now()->setTimestamp(intval($ts / 1000))->toDateTimeString(),
            ];
        }

        return $events;
    }
}
