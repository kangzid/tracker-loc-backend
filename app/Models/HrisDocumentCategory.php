<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisDocumentCategory extends Model
{
    use HasFactory;

    protected $table = 'hris_document_categories';
    protected $fillable = ['tenant_id', 'name', 'code', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
