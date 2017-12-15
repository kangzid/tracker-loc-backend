<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HrisEmployeeAllowanceItem extends Model
{
    protected $fillable = ['employee_allowance_id', 'allowance_type_id', 'amount'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function allowanceType()
    {
        return $this->belongsTo(HrisAllowanceType::class, 'allowance_type_id');
    }
}
