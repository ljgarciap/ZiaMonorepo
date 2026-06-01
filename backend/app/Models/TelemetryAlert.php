<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryAlert extends Model
{
    protected $fillable = [
        'device_id',
        'alert_type',
        'severity',
        'message',
        'threshold_value',
        'actual_value',
        'detected_at',
        'resolved'
    ];

    protected $casts = [
        'threshold_value' => 'double',
        'actual_value' => 'double',
        'detected_at' => 'datetime',
        'resolved' => 'boolean'
    ];

    public function device()
    {
        return $this->belongsTo(IotDevice::class, 'device_id');
    }
}
