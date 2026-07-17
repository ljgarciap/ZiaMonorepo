<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class IotDevice extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'thingsboard_id',
        'name',
        'type',
        'location',
        'unit',
        'company_id',
        'emission_factor_id',
        'baseline_kwh',
        'office_hours_start',
        'office_hours_end',
        'last_calibrated_at',
        'calibration_notes',
        'registered_by',
        'operational_unit_id',
        'last_raw_value',
        'last_synced_at',
    ];

    protected $casts = [
        'last_calibrated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function readings()
    {
        return $this->hasMany(TelemetryReading::class, 'device_id');
    }

    public function alerts()
    {
        return $this->hasMany(TelemetryAlert::class, 'device_id');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function operationalUnit()
    {
        return $this->belongsTo(\App\Models\OperationalUnit::class, 'operational_unit_id');
    }

    public function emissionFactor()
    {
        return $this->belongsTo(\App\Models\EmissionFactor::class, 'emission_factor_id');
    }
}
