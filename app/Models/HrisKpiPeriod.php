<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisKpiPeriod extends Model
{
    use HasFactory;

    protected $table = 'hris_kpi_periods';
    protected $fillable = ['tenant_id', 'name', 'period_type', 'start_date', 'end_date', 'status', 'description'];
    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];
}
