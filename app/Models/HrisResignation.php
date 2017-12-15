<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisResignation extends Model
{
    use HasFactory;

    protected $table = 'hris_resignations';
    protected $fillable = [
        'tenant_id',
        'employee_id',
        'resignation_number',
        'category',
        'resignation_date',
        'last_working_date',
        'reason',
        'document_path',
        'document_name',
        'document_path',
        'status',
        'created_by',
    ];

    protected $casts = [
        'resignation_date' => 'date:Y-m-d',
        'last_working_date' => 'date:Y-m-d',
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
