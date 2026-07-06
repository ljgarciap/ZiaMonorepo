<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_document_id',
        'company_id',
        'chunk_index',
        'content',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(CompanyDocument::class, 'company_document_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
