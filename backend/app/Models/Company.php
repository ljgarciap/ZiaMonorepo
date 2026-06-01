<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\LogsActivity;
use App\Models\User;

class Company extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'nit', 'company_sector_id', 'logo_url', 'tags'];

    protected $casts = [
        'tags' => 'array'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }

    public function sector()
    {
        return $this->belongsTo(CompanySector::class, 'company_sector_id');
    }

    public function periods()
    {
        return $this->hasMany(Period::class);
    }

    public function factors()
    {
        return $this->belongsToMany(EmissionFactor::class, 'company_emission_factor')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }
}
