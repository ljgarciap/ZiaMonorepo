<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionnaireQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'template_id', 'question_text', 'question_type', 'options',
        'unit', 'scope_hint', 'category_hint', 'required', 'help_text', 'order',
    ];

    protected $casts = [
        'options'  => 'array',
        'required' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(QuestionnaireTemplate::class, 'template_id');
    }
}
