<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisTraining extends Model
{
    use HasFactory;

    protected $table = 'hris_trainings';

    protected $fillable = [
        'tenant_id',
        'title',
        'category',
        'trainer_name',
        'training_date',
        'location_or_link',
        'duration_hours',
        'status',
        'description',
    ];

    protected $casts = [
        'training_date' => 'date',
        'duration_hours' => 'integer',
    ];

    public function participants()
    {
        return $this->hasMany(HrisTrainingParticipant::class, 'training_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
