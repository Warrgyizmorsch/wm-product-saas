<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class AttendanceCorrection extends BaseModel
{
    protected $table = 'attendance_corrections';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'attendance_id',
        'date',
        'requested_check_in',
        'requested_check_out',
        'reason',
        'status',
        'rejected_reason',
        'approved_by',
        'approved_at',
        'cancellation_reason',
        'cancellation_requested_at',
        'cancellation_decided_by',
        'cancellation_decided_at',
        'cancellation_denial_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'requested_check_in' => 'datetime',
        'requested_check_out' => 'datetime',
        'approved_at' => 'datetime',
        'cancellation_requested_at' => 'datetime',
        'cancellation_decided_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
