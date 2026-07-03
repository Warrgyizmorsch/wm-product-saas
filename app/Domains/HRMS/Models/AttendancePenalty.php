<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class AttendancePenalty extends Model
{
    protected $fillable = [
        'company_id',
        'rule_type',
        'grace_period_minutes',
        'threshold_count',
        'penalty_action',
        'leave_type_id',
        'penalty_value',
        'status',
        'penalty_tiers',
    ];

    protected $casts = [
        'status' => 'boolean',
        'penalty_value' => 'decimal:2',
        'penalty_tiers' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
