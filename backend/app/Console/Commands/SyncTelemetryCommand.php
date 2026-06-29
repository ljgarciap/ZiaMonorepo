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
            $metricName = $device->type === 'energy' ? 'electricity_kwh' : 'water_m3';
            $this->info("Consultando telemetría para dispositivo: {$device->name} (Tipo: {$device->type})...");

            $telemetry = $this->thingsBoardService->getLatestTelemetry(
                $device->thingsboard_id ?? 'default_id', 
                $metricName
            );

            // 3. Save telemetry reading
            $reading = \App\Models\TelemetryReading::create([
                'device_id' => $device->id,
                'metric_name' => $metricName,
                'value' => $telemetry['value'],
                'timestamp' => $telemetry['timestamp']
            ]);

            $this->line("-> Lectura guardada: {$reading->value} {$device->unit} a las {$reading->timestamp}");

            // 4. Evaluate alerts
            $this->alertService->checkReading($reading);

            // 5. Ingest into carbon emissions (idempotent accumulation for the active period)
            $emission = $this->ingestionService->ingestReading($reading);
            if ($emission) {
                $this->line("-> Emisión actualizada: {$emission->calculated_co2e} tCO2e (período {$emission->period->year ?? '?'})");
            }
        }

        $this->info('Sincronización de telemetría completada con éxito.');
        return 0;
    }
}
