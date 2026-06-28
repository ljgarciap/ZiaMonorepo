<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyGroup extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'created_by'];

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_group_members', 'group_id', 'company_id')
                    ->withPivot('joined_at')
                    ->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
