<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Domains\HRMS\Traits\HasHrmsScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeRequest extends BaseModel
{
    use SoftDeletes, HasHrmsScope;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'employee_id',
        'date',
        'start_time',
        'end_time',
        'duration_hours',
        'approved_duration_hours',
        'compensation_type',
        'reason',
        'status',
        'approved_by',
        'rejection_reason',
        'cancellation_reason',
        'attachment_path',
    ];

    protected $casts = [
        'date' => 'date',
        'duration_hours' => 'decimal:2',
        'approved_duration_hours' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /** Employee can directly withdraw only if still pending */
    public function canWithdraw(): bool
    {
        return $this->status === 'pending';
    }

    /** Employee can request cancellation only if already approved */
    public function canRequestCancellation(): bool
    {
        return $this->status === 'approved';
    }
}
