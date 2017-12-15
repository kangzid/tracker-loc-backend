<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HrisEmployeeSalary extends Model
{
    protected $fillable = ['tenant_id', 'employee_id', 'code', 'wage_type', 'amount', 'effective_date'];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
