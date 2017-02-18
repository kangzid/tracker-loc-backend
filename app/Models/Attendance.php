<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'check_in_lat',
        'check_in_lng',
        'check_out_lat',
        'check_out_lng',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in' => 'datetime:H:i:s',
            'check_out' => 'datetime:H:i:s',
            'check_in_lat' => 'decimal:8',
            'check_in_lng' => 'decimal:8',
            'check_out_lat' => 'decimal:8',
            'check_out_lng' => 'decimal:8',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}