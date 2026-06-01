<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\LogsActivity;

class EmissionCategory extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'scope_id', 'parent_id', 'description'];

    public function scope()
    {
        return $this->belongsTo(Scope::class);
    }

    public function parent()
    {
        return $this->belongsTo(EmissionCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(EmissionCategory::class, 'parent_id');
    }

    public function factors()
    {
        return $this->hasMany(EmissionFactor::class);
    }
}
