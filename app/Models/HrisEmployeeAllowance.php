<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HrisEmployeeAllowance extends Model
{
    protected $fillable = ['tenant_id', 'employee_id', 'code', 'effective_date'];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function items()
    {
        return $this->hasMany(HrisEmployeeAllowanceItem::class, 'employee_allowance_id');
    }
}
