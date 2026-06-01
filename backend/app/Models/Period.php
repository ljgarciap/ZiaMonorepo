<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class Period extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['company_id', 'year', 'status'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function emissions()
    {
        return $this->hasMany(CarbonEmission::class);
    }
}
