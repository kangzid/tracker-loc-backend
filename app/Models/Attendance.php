<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'admin_id',
        'employee_id',
        'shift_id',
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

    // OPTIMIZATION: Eager load employee relationship by default
    protected $with = [];

    // OPTIMIZATION: Query Scopes for common filters
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('date', Carbon::today());
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('date', Carbon::now()->month)
                     ->whereYear('date', Carbon::now()->year);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    // OPTIMIZATION: Use index for employee relationship
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(HrisShift::class, 'shift_id');
    }

}
