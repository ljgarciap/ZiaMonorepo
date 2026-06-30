<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectorQuestionnaireRule extends Model
{
    protected $fillable = [
        'sector_code',
        'subsector_code',
        'emission_factor_id',
        'questionnaire_label',
        'variable_name',
        'input_unit_hint',
        'is_required',
        'display_order',
        'help_text',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function emissionFactor()
    {
        return $this->belongsTo(EmissionFactor::class);
    }
}
