<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class CompanySector extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'code', 'description', 'ciiu_code', 'is_ciiu'];

    protected $casts = ['is_ciiu' => 'boolean'];

    public function companies()
    {
        return $this->hasMany(Company::class, 'company_sector_id');
    }
}
