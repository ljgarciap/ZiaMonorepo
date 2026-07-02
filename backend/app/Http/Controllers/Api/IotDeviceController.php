<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\IotDevice;
use App\Models\TelemetryAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IotDeviceController extends Controller
{
    /**
     * GET /companies/{company}/iot-devices
     * Registro y estado de configuración de los dispositivos de una empresa
     * (distinto de /telemetry/live, que trae solo las últimas lecturas).
     */
    public function index(Company $company)
    {
        $this->authorizeCompanyAccess($company);

        $devices = $company->iotDevices()
            ->withCount([
                'readings',
                'alerts as pending_alerts_count' => fn ($q) => $q->where('resolved', false),
            ])
            ->get();

        return response()->json($devices);
    }

    /**
     * POST /companies/{company}/iot-devices
     * Registrar y enlazar un nuevo dispositivo (Técnico IoT / Superadmin).
     */
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompanyAccess($company);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'location' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'thingsboard_id' => 'nullable|string|max:255|unique:iot_devices,thingsboard_id',
            'emission_factor_id' => 'nullable|exists:emission_factors,id',
            'baseline_kwh' => 'nullable|numeric',
            'office_hours_start' => 'nullable|string|max:5',
            'office_hours_end' => 'nullable|string|max:5',
            'operational_unit_id' => ['nullable', Rule::exists('operational_units', 'id')->where('company_id', $company->id)],
        ]);

        $device = $company->iotDevices()->create($data + [
            'registered_by' => Auth::id(),
        ]);

        return response()->json($device, 201);
    }

    /**
     * PUT /iot-devices/{device}
     * Actualizar metadatos/configuración del dispositivo (etiquetas técnicas,
     * ubicación, protocolo, unidad, factor asociado).
     */
    public function update(Request $request, IotDevice $device)
    {
        $this->authorizeCompanyAccess($device->company);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:100',
            'location' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'thingsboard_id' => 'nullable|string|max:255|unique:iot_devices,thingsboard_id,' . $device->id,
            'emission_factor_id' => 'nullable|exists:emission_factors,id',
            'baseline_kwh' => 'nullable|numeric',
            'office_hours_start' => 'nullable|string|max:5',
            'office_hours_end' => 'nullable|string|max:5',
            'operational_unit_id' => ['nullable', Rule::exists('operational_units', 'id')->where('company_id', $device->company_id)],
        ]);

        $device->update($data);

        return response()->json($device);
    }

    /**
     * DELETE /iot-devices/{device}
     */
    public function destroy(IotDevice $device)
    {
        $this->authorizeCompanyAccess($device->company);
        $device->delete();
        return response()->json(null, 204);
    }

    /**
     * POST /iot-devices/{device}/calibrate
     * Registrar el resultado de una prueba de calibración.
     */
    public function calibrate(Request $request, IotDevice $device)
    {
        $this->authorizeCompanyAccess($device->company);

        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $device->update([
            'last_calibrated_at' => now(),
            'calibration_notes' => $data['notes'] ?? null,
        ]);

        return response()->json($device);
    }

    /**
     * POST /telemetry/alerts/{alert}/resolve
     * Diagnosticar y cerrar una alerta de telemetría (lectura anómala, pérdida de señal).
     */
    public function resolveAlert(Request $request, TelemetryAlert $alert)
    {
        $this->authorizeCompanyAccess($alert->device->company);

        $data = $request->validate([
            'resolution_note' => 'nullable|string',
        ]);

        $alert->update([
            'resolved' => true,
            'resolution_note' => $data['resolution_note'] ?? null,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return response()->json($alert);
    }

    /**
     * El Técnico IoT solo debe operar sobre empresas/proyectos a los que está
     * asignado (company_user); el Superadmin no tiene esa restricción.
     */
    private function authorizeCompanyAccess(?Company $company): void
    {
        abort_if(!$company, 404);

        $user = Auth::user();

        if ($user->role === 'superadmin') {
            return;
        }

        $belongs = $user->companies()
            ->where('companies.id', $company->id)
            ->wherePivot('is_active', true)
            ->exists();

        abort_unless($belongs, 403, 'No tienes acceso a esta empresa.');
    }
}
