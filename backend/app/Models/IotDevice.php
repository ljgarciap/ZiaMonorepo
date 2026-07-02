<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IotDevice extends Model
{
    use HasFactory, SoftDeletes;

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
    ];

    protected $casts = [
        'last_calibrated_at' => 'datetime',
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

    public function emissionFactor()
    {
        return $this->belongsTo(\App\Models\EmissionFactor::class, 'emission_factor_id');
    }
}
