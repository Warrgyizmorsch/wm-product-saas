<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveEncashment extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'employee_id',
        'leave_type_id',
        'requested_days',
        'status',
        'reason',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'requested_days' => 'decimal:1',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
