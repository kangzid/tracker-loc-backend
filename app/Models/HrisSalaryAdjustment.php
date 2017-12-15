<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HrisSalaryAdjustment extends Model
{
    protected $fillable = ['batch_id', 'employee_id', 'type', 'amount', 'notes'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function batch()
    {
        return $this->belongsTo(HrisSalaryAdjustmentBatch::class, 'batch_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
