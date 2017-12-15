<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisTrainingParticipant extends Model
{
    use HasFactory;

    protected $table = 'hris_training_participants';

    protected $fillable = [
        'training_id',
        'employee_id',
        'attendance_status',
        'score',
        'passed',
        'certificate_number',
        'certificate_path', 'certificate_name',
        'notes',
    ];

    protected $casts = [
        'score' => 'integer',
        'passed' => 'boolean',
    ];

    public function training()
    {
        return $this->belongsTo(HrisTraining::class, 'training_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
