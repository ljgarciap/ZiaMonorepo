<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'period_id',
        'user_id',
        'body',
        'verdict',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
