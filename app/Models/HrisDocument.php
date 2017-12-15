<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisDocument extends Model
{
    use HasFactory;

    protected $table = 'hris_documents';

        protected $fillable = [
        'tenant_id',
        'employee_id',
        'title',
        'category',
        'document_name',
        'document_path',
        'document_path',
        'file_name',
        'file_type',
        'file_size_kb',
        'physical_location',
        'is_original_stored',
        'is_verified',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'is_original_stored' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
