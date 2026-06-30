<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subsector extends Model
{
    protected $fillable = ['company_sector_id', 'code', 'name', 'description'];

    public function sector()
    {
        return $this->belongsTo(CompanySector::class, 'company_sector_id');
    }

    public function questionnaireRules()
    {
        return $this->hasMany(SectorQuestionnaireRule::class, 'subsector_code', 'code');
    }
}
