<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'is_exceptional',
        'model',
        'model_id',
        'details',
        'ip_address'
    ];

    protected $casts = [
        'details' => 'array',
        'is_exceptional' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
