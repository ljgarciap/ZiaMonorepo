<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyDocument extends Model
{
    use HasFactory;

    // Sin SoftDeletes a propósito: destroy() ya borra el archivo físico de
    // disco de forma irreversible, y document_chunks depende de un DELETE
    // real (no soft-delete) para que el cascadeOnDelete de la FK dispare.

    protected $fillable = [
        'company_id',
        'uploaded_by',
        'title',
        'file_path',
        'mime_type',
        'status',
        'error_message',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
