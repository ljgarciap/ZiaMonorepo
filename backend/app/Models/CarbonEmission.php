<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\LogsActivity;

class CarbonEmission extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'period_id',
        'emission_factor_id',
        'quantity',
        'emissions_co2',
        'emissions_ch4',
        'emissions_n2o',
        'emissions_nf3',
        'emissions_sf6',
        'calculated_co2e',
        'uncertainty_result',
        'activity_data_total',
        'activity_data_stdev',
        'notes'
    ];

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function factor()
    {
        return $this->belongsTo(EmissionFactor::class, 'emission_factor_id');
    }
}
