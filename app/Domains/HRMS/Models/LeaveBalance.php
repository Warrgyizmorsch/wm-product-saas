<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'company_id',
        'employee_id',
        'leave_type_id',
        'allocated',
        'used'
    ];

    protected $casts = [
        'allocated' => 'decimal:1',
        'used' => 'decimal:1'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function getRemainingAttribute(): float
    {
        return max(0.0, floatval($this->allocated) - floatval($this->used));
    }
}
