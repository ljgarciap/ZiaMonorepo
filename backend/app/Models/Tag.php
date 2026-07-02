<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;

class Tag extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'company_sector_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sector()
    {
        return $this->belongsTo(CompanySector::class, 'company_sector_id');
    }
}
