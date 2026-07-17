<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IotDevice;
use App\Models\TelemetryReading;
use App\Services\ThingsBoardService;
use App\Services\TelemetryAlertService;
use App\Services\IoTCarbonIngestionService;
use Illuminate\Support\Facades\Log;

class SyncTelemetryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zia:sync-telemetry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza lecturas de telemetría IoT del edificio ECONOVA desde ThingsBoard a la base de datos PostgreSQL';

    protected $thingsBoardService;
    protected $alertService;
    protected $ingestionService;

    public function __construct(
        ThingsBoardService $thingsBoardService,
        TelemetryAlertService $alertService,
        IoTCarbonIngestionService $ingestionService
    ) {
        parent::__construct();
        $this->thingsBoardService = $thingsBoardService;
        $this->alertService       = $alertService;
        $this->ingestionService   = $ingestionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronización de telemetría IoT...');

        // 1. Auto-seed default IoT devices if none exist
        if (\Schema::hasTable('iot_devices') && \App\Models\IotDevice::count() === 0) {
            $this->info('Sembrando dispositivos IoT por defecto...');

            $company       = \App\Models\Company::where('name', 'like', '%ECONOVA%')->first();
            $energyFactor  = \App\Models\EmissionFactor::where('name', 'like', '%Interconectado%')->first();
            $waterFactor   = \App\Models\EmissionFactor::where('name', 'like', '%Agua Potable Consumida%')->first();

            \App\Models\IotDevice::create([
                'thingsboard_id'    => env('THINGSBOARD_ENERGY_DEVICE_ID', 'energy_econova_device'),
                'name'              => 'Medidor Eléctrico General - ECONOVA',
                'type'              => 'energy',
                'location'          => 'Edificio ECONOVA - Tablero Principal',
                'unit'              => 'kWh',
                'company_id'        => $company?->id,
                'emission_factor_id'=> $energyFactor?->id,
            ]);

            \App\Models\IotDevice::create([
                'thingsboard_id'    => env('THINGSBOARD_WATER_DEVICE_ID', 'water_econova_device'),
                'name'              => 'Medidor de Agua Principal - ECONOVA',
                'type'              => 'water',
                'location'          => 'Edificio ECONOVA - Entrada General de Agua',
                'unit'              => 'm3',
                'company_id'        => $company?->id,
                'emission_factor_id'=> $waterFactor?->id,
            ]);
        }

        // 2. Fetch all devices
        $devices = \App\Models\IotDevice::all();
        if ($devices->isEmpty()) {
            $this->warn('No se encontraron dispositivos registrados.');
            return 0;
        }

        foreach ($devices as $device) {
            $this->info("Consultando telemetría para dispositivo: {$device->name} (Tipo: {$device->type})...");

            match ($device->type) {
                'energy' => $this->syncCumulativeEnergyDevice($device),
                'waste'  => $this->syncEventBasedDevice($device, 'weight_kg'),
                // 'water' y cualquier tipo no reconocido: comportamiento original sin cambios.
                default  => $this->syncIntervalDevice($device, 'water_m3'),
            };
        }

        $this->info('Sincronización de telemetría completada con éxito.');
        return 0;
    }

    /**
     * Dispositivos cuyo valor de telemetría YA representa el consumo del
     * intervalo (no un contador acumulado) — comportamiento original, sin
     * cambios. Hoy aplica a 'water' y a cualquier tipo no reconocido.
     */
    private function syncIntervalDevice(IotDevice $device, string $metricName): void
    {
        $telemetry = $this->thingsBoardService->getLatestTelemetry(
            $device->thingsboard_id ?? 'default_id',
            $metricName
        );

        $reading = TelemetryReading::create([
            'device_id' => $device->id,
            'metric_name' => $metricName,
            'value' => $telemetry['value'],
            'timestamp' => $telemetry['timestamp'],
        ]);

        $this->line("-> Lectura guardada: {$reading->value} {$device->unit} a las {$reading->timestamp}");
        $this->processReading($reading);
    }

    /**
     * Medidores que exponen un contador acumulado (ej. energy_active_import_wh
     * del medidor real, en Wh de por vida). Se guarda el delta contra la última
     * lectura cruda conocida, no el valor crudo — así el resto del pipeline
     * (que suma TelemetryReading.value del año) sigue siendo correcto.
     */
    private function syncCumulativeEnergyDevice(IotDevice $device): void
    {
        $metricName = 'energy_active_import_wh';
        $telemetry = $this->thingsBoardService->getLatestTelemetry(
            $device->thingsboard_id ?? 'default_id',
            $metricName
        );
        $currentRaw = $telemetry['value'];

        // Primera lectura tras conectar el dispositivo: no hay línea base para
        // calcular un delta real. Se descarta (delta 0) en vez de contar de
        // golpe todo el histórico acumulado del medidor desde su instalación.
        $deltaKwh = $device->last_raw_value === null
            ? 0.0
            : max(0.0, ($currentRaw - $device->last_raw_value) / 1000);

        $device->update(['last_raw_value' => $currentRaw]);

        $reading = TelemetryReading::create([
            'device_id' => $device->id,
            'metric_name' => $metricName,
            'value' => $deltaKwh,
            'timestamp' => $telemetry['timestamp'],
        ]);

        $this->line("-> Lectura guardada: {$reading->value} kWh (delta del intervalo) a las {$reading->timestamp}");
        $this->processReading($reading);
    }

    /**
     * Sensores que reportan por evento discreto y resetean entre eventos (ej.
     * báscula de peso). "Última lectura" puede caer justo entre dos eventos y
     * perderlos, así que se pide el rango completo desde la última sync
     * exitosa. El watermark (last_synced_at) solo avanza si el rango se pudo
     * leer con éxito — si ThingsBoard falla, se reintenta ese mismo rango en
     * la próxima corrida en vez de saltárselo.
     */
    private function syncEventBasedDevice(IotDevice $device, string $metricName): void
    {
        $endTs = now();

        if ($device->last_synced_at === null) {
            $device->update(['last_synced_at' => $endTs]);
            $this->line("-> {$device->name}: primera sincronización, sin ventana previa — no se procesan eventos retroactivos.");
            return;
        }

        $events = $this->thingsBoardService->getTimeseriesRange(
            $device->thingsboard_id ?? 'default_id',
            $metricName,
            (int) ($device->last_synced_at->timestamp * 1000),
            (int) ($endTs->timestamp * 1000)
        );

        if ($events === null) {
            $this->warn("-> {$device->name}: no se pudo leer el rango de eventos, se reintentará en la próxima corrida.");
            return;
        }

        foreach ($events as $event) {
            $reading = TelemetryReading::create([
                'device_id' => $device->id,
                'metric_name' => $metricName,
                'value' => $event['value'],
                'timestamp' => $event['timestamp'],
            ]);
            $this->processReading($reading);
        }

        $this->line("-> {$device->name}: " . count($events) . ' evento(s) procesados.');
        $device->update(['last_synced_at' => $endTs]);
    }

    /**
     * Pasos comunes tras guardar una TelemetryReading: evaluar alertas e
     * ingerir a huella de carbono.
     */
    private function processReading(TelemetryReading $reading): void
    {
        $this->alertService->checkReading($reading);

        $emission = $this->ingestionService->ingestReading($reading);
        if ($emission) {
            $year = $emission->period->year ?? '?';
            $this->line("-> Emisión actualizada: {$emission->calculated_co2e} tCO2e (período {$year})");
        }
    }
}
