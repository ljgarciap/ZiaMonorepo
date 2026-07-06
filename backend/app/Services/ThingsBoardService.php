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
        $this->host = SystemSetting::resolve('THINGSBOARD_HOST', 'https://thingsboard.cloud');
        $this->username = SystemSetting::resolve('THINGSBOARD_USERNAME');
        $this->password = SystemSetting::resolve('THINGSBOARD_PASSWORD');
        // THINGSBOARD_MOCK es una bandera de entorno, no una credencial — se
        // queda en .env, no es gestionable desde la UI de API Keys.
        $this->mockMode = filter_var(env('THINGSBOARD_MOCK', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get the latest telemetry value for a device and key.
     *
     * @param string $deviceId ThingsBoard UUID of the device
     * @param string $metricName Metric name to retrieve
     * @return array Contains 'value', 'timestamp'
     */
    public function getLatestTelemetry(string $deviceId, string $metricName): array
    {
        if ($this->mockMode) {
            return $this->generateMockTelemetry($deviceId, $metricName);
        }

        try {
            $token = $this->getAuthToken();
            if (!$token) {
                Log::error('ThingsBoard auth failed. Falling back to mock data.');
                return $this->generateMockTelemetry($deviceId, $metricName);
            }

            // Get latest values from ThingsBoard API
            $response = Http::withHeaders([
                'X-Authorization' => 'Bearer ' . $token
            ])->get("{$this->host}/api/plugins/telemetry/DEVICE/{$deviceId}/values/timeseries", [
                'keys' => $metricName
            ]);

            if ($response->successful() && isset($response->json()[$metricName][0])) {
                $data = $response->json()[$metricName][0];
                return [
                    'value' => floatval($data['value']),
                    // ThingsBoard timestamps are in milliseconds
                    'timestamp' => now()->setTimestamp(intval($data['ts'] / 1000))->toDateTimeString()
                ];
            }

            Log::warning("No telemetry found for device {$deviceId} and key {$metricName} in ThingsBoard. Using simulated fallback.");
            return $this->generateMockTelemetry($deviceId, $metricName);
        } catch (\Exception $e) {
            Log::error("ThingsBoard API error: " . $e->getMessage() . ". Using simulated fallback.");
            return $this->generateMockTelemetry($deviceId, $metricName);
        }
    }

    /**
     * Fetch or retrieve cached JWT Auth token from ThingsBoard.
     */
    protected function getAuthToken(): ?string
    {
        return Cache::remember('thingsboard_jwt_token', 55, function () {
            try {
                $response = Http::post("{$this->host}/api/auth/login", [
                    'username' => $this->username,
                    'password' => $this->password
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
}
