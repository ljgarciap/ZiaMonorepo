<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectricityFactor extends Model
{
    protected $fillable = ['year', 'region_code', 'value_kgco2e', 'source'];

    public static function forYear(int $year, string $region = 'CO'): ?self
    {
        return static::where('year', $year)->where('region_code', $region)->first();
    }
}
