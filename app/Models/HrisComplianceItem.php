<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class HrisComplianceItem extends Model
{
    use HasFactory;

    protected $table = 'hris_compliance_items';

    protected $fillable = [
        'tenant_id',
        'target_type',
        'target_id',
        'doc_name',
        'doc_number',
        'expiry_date',
        'status',
        'reminder_days_before',
        'document_path',
        'renewed_at',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'renewed_at' => 'date',
        'reminder_days_before' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'target_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'target_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Dynamically compute current status based on expiry date
     */
    public function calculateCurrentStatus(): string
    {
        $today = Carbon::today();
        $expiry = Carbon::parse($this->expiry_date);
        $diffDays = $today->diffInDays($expiry, false);

        if ($diffDays < 0) {
            return 'expired';
        } elseif ($diffDays <= ($this->reminder_days_before ?? 30)) {
            return 'warning';
        } else {
            return 'safe';
        }
    }
}
