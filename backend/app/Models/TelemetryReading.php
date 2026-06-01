<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryReading extends Model
{
    protected $fillable = [
        'device_id',
        'metric_name',
        'value',
        'timestamp'
    ];

    protected $casts = [
        'value' => 'double',
        'timestamp' => 'datetime'
    ];

    public function device()
    {
        return $this->belongsTo(IotDevice::class, 'device_id');
    }
}
