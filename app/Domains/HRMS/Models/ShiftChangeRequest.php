<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Domains\Production\Models\ProductionShift;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftChangeRequest extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'recurring_days',
        'current_shift_id',
        'requested_shift_id',
        'reason',
        'status',
        'approved_by',
        'rejection_reason',
        'attachment_path',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'recurring_days' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currentShift(): BelongsTo
    {
        return $this->belongsTo(ProductionShift::class, 'current_shift_id');
    }

    public function requestedShift(): BelongsTo
    {
        return $this->belongsTo(ProductionShift::class, 'requested_shift_id');
    }

    public function approvedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
