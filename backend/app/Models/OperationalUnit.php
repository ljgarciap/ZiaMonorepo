<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['company_id', 'name', 'description'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_user', 'unit_id', 'user_id')
            ->withPivot('company_id', 'role', 'is_active')
            ->withTimestamps();
    }

    public function emissions()
    {
        return $this->hasMany(CarbonEmission::class, 'unit_id');
    }
}
