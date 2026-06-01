<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\LogsActivity;

class Scope extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'description', 'documentation_text'];

    public function categories()
    {
        return $this->hasMany(EmissionCategory::class);
    }
}
