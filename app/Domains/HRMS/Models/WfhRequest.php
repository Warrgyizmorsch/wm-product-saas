<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WfhRequest extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'employee_id',
        'start_date',
        'end_date',
        'duration',
        'start_date_type',
        'end_date_type',
        'notified_contacts',
        'reason',
        'wfh_latitude',
        'wfh_longitude',
        'status',
        'current_level',
        'approved_by',
        'rejection_reason',
        'cancellation_reason',
        'attachment_path',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'duration' => 'decimal:1',
        'notified_contacts' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

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
