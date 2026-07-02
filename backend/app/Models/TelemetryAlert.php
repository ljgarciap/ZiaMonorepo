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
        'resolved',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'threshold_value' => 'double',
        'actual_value' => 'double',
        'detected_at' => 'datetime',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IotDevice::class, 'device_id');
    }
}
