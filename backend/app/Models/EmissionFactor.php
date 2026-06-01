<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\LogsActivity;

class EmissionFactor extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'emission_category_id',
        'calculation_formula_id',
        'measurement_unit_id',
        'name',
        // 'unit', // Removed in favor of relation
        'factor_co2',
        'factor_ch4',
        'factor_n2o',
        'factor_nf3',
        'factor_sf6',
        'factor_total_co2e',
        'uncertainty_lower',
        'uncertainty_upper',
        'uncertainty_distribution',
        'source_reference'
    ];

    public function unit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id');
    }

    public function formula()
    {
        return $this->belongsTo(CalculationFormula::class, 'calculation_formula_id');
    }

    public function category()
    {
        return $this->belongsTo(EmissionCategory::class, 'emission_category_id');
    }

    public function emissions()
    {
        return $this->hasMany(CarbonEmission::class);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_emission_factor')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }
}
