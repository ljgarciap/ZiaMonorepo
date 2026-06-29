<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class CalculationFormula extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'expression', 'variables', 'description'];

    protected $casts = [
        'variables' => 'array',
    ];

    public function emissionFactors()
    {
        return $this->hasMany(EmissionFactor::class);
    }
}
