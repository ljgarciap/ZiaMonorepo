<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulatorScenario extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'category', 'scope',
        'reduction_kwh_year', 'emission_factor_kgco2e_kwh', 'tariff_cop_kwh',
        'reduction_kg_year', 'gwp',
        'annual_co2e_tco2e', 'annual_savings_cop', 'is_active',
    ];

    protected $casts = [
        'scope'                   => 'integer',
        'reduction_kwh_year'      => 'float',
        'emission_factor_kgco2e_kwh' => 'float',
        'tariff_cop_kwh'          => 'float',
        'reduction_kg_year'       => 'float',
        'gwp'                     => 'integer',
        'annual_co2e_tco2e'       => 'float',
        'annual_savings_cop'      => 'integer',
        'is_active'               => 'boolean',
    ];
}
