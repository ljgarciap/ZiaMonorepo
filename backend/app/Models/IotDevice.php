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
        'unit'
    ];

    public function readings()
    {
        return $this->hasMany(TelemetryReading::class, 'device_id');
    }

    public function alerts()
    {
        return $this->hasMany(TelemetryAlert::class, 'device_id');
    }
}
