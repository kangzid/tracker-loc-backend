<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisPerformanceReview extends Model
{
    use HasFactory;

    protected $table = 'hris_performance_reviews';

    protected $fillable = [
        'tenant_id',
        'target_type',
        'employee_id',
        'department',
        'period',
        'score',
        'grade',
        'attendance_score',
        'task_completion_score',
        'discipline_score',
        'teamwork_score',
        'remarks',
        'reviewer_id',
        'status',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'attendance_score' => 'decimal:2',
        'task_completion_score' => 'decimal:2',
        'discipline_score' => 'decimal:2',
        'teamwork_score' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
