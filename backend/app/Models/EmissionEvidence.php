<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmissionEvidence extends Model
{
    protected $fillable = [
        'carbon_emission_id',
        'user_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'description',
    ];

    public function emission()
    {
        return $this->belongsTo(CarbonEmission::class, 'carbon_emission_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
