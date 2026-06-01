<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\LogsActivity;

class MeasurementUnit extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'symbol'];

    public function factors()
    {
        return $this->hasMany(EmissionFactor::class);
    }
}
