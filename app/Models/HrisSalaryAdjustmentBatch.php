<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HrisSalaryAdjustmentBatch extends Model
{
    protected $fillable = ['tenant_id', 'code', 'month', 'year'];

    public function items()
    {
        return $this->hasMany(HrisSalaryAdjustment::class, 'batch_id');
    }
}
