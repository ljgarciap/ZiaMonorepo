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
        'user_id',
        'unit_id',
        'emission_factor_id',
        'source',
        'quantity',
        'emissions_co2',
        'emissions_ch4',
        'emissions_n2o',
        'emissions_nf3',
        'emissions_sf6',
        'calculated_co2e',
        'biogenic_co2e',
        'carbon_stored',
        'avoided_emissions',
        'scope2_method',
        'uncertainty_result',
        'activity_data_total',
        'activity_data_stdev',
        'notes',
        'monthly_data',
        'validation_status',
        'validation_notes',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'monthly_data' => 'array',
    ];

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function evidences()
    {
        return $this->hasMany(EmissionEvidence::class, 'carbon_emission_id');
    }

    public function factor()
    {
        return $this->belongsTo(EmissionFactor::class, 'emission_factor_id');
    }
}
