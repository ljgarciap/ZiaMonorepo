<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class QuestionnaireTemplate extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'title', 'description', 'sector', 'status', 'version', 'approved_by', 'approved_at',
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function questions()
    {
        return $this->hasMany(QuestionnaireQuestion::class, 'template_id')->orderBy('order');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
