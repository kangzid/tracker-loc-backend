<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisMutation extends Model
{
    use HasFactory;

    protected $table = 'hris_mutations';
    protected $fillable = [
        'tenant_id',
        'employee_id',
        'mutation_number',
        'mutation_type',
        'effective_date',
        'old_department',
        'new_department',
        'old_position',
        'new_position',
        'old_employment_status',
        'new_employment_status',
        'reason',
        'document_sk_path',
        'document_sk_name',
        'document_sk_base64',
        'status',
        'created_by',
    ];

    protected $casts = [
        'effective_date' => 'date:Y-m-d',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
