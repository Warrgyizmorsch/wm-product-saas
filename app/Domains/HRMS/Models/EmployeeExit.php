<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeExit extends BaseModel
{
    protected $table = 'employee_exits';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'separation_type',
        'resignation_date',
        'preferred_lwd',
        'approved_lwd',
        'notice_period_days',
        'notice_shortfall_days',
        'notice_action',
        'reason_category',
        'reason_details',
        'status',
        'initiated_by',
        'approved_by',
        'approved_at',
        'exit_interview_notes',
        'exit_interview_rating',
    ];

    protected $casts = [
        'resignation_date' => 'date',
        'preferred_lwd' => 'date',
        'approved_lwd' => 'date',
        'approved_at' => 'datetime',
        'notice_period_days' => 'integer',
        'notice_shortfall_days' => 'integer',
        'exit_interview_rating' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(EmployeeExitClearance::class, 'employee_exit_id');
    }

    public function fnfSettlement(): HasOne
    {
        return $this->hasOne(EmployeeFnfSettlement::class, 'employee_exit_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeExitDocument::class, 'employee_exit_id');
    }

    public function getEffectiveLwdAttribute(): ?\Carbon\Carbon
    {
        return $this->approved_lwd ? \Carbon\Carbon::parse($this->approved_lwd) : ($this->preferred_lwd ? \Carbon\Carbon::parse($this->preferred_lwd) : ($this->resignation_date ? \Carbon\Carbon::parse($this->resignation_date)->addDays((int) ($this->notice_period_days ?? 30)) : null));
    }

    public function isFullyCleared(): bool
    {
        if ($this->clearances()->count() === 0) {
            return false;
        }
        return $this->clearances()->where('status', 'pending')->count() === 0;
    }

    public function getClearanceProgressPercentage(): int
    {
        $total = $this->clearances()->count();
        if ($total === 0) {
            return 0;
        }
        $reviewed = $this->clearances()->where('status', '!=', 'pending')->count();
        return (int) round(($reviewed / $total) * 100);
    }
}
