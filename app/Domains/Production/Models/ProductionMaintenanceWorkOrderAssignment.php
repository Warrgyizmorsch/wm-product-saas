<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaintenanceWorkOrderAssignment extends BaseModel
{
    use HasFactory;

    protected $table = 'production_maintenance_work_order_assignments';

    public const TYPE_INTERNAL = 'internal';
    public const TYPE_EXTERNAL = 'external';

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'technician_id',
        'technician_name',
        'assignment_type',
        'assigned_at',
        'expected_work_hours',
        'worked_hours',
        'hourly_rate',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'expected_work_hours' => 'decimal:2',
        'worked_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionMaintenanceWorkOrder::class, 'work_order_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function getEffectiveWorkedHoursAttribute(): float
    {
        $workOrder = $this->workOrder;
        if (! $workOrder) {
            return (float) ($this->worked_hours ?: 0.0);
        }

        if (in_array($workOrder->status, [ProductionMaintenanceWorkOrder::STATUS_COMPLETED, ProductionMaintenanceWorkOrder::STATUS_CANCELLED])) {
            return (float) ($this->worked_hours ?: 0.0);
        }

        if ($workOrder->status === ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS) {
            $maintStart = $workOrder->actual_start
                ? \Carbon\Carbon::parse($workOrder->actual_start)
                : ($workOrder->planned_start ? \Carbon\Carbon::parse($workOrder->planned_start) : \Carbon\Carbon::parse($workOrder->created_at));

            $assignmentAt = $this->assigned_at ? \Carbon\Carbon::parse($this->assigned_at) : $maintStart;
            $referenceStart = $assignmentAt->greaterThan($maintStart) ? $assignmentAt : $maintStart;
            $seconds = max(0, $referenceStart->diffInSeconds(now(), false));
            $accumulated = round($seconds / 3600, 2);

            return max((float) ($this->worked_hours ?: 0.0), $accumulated);
        }

        return (float) ($this->worked_hours ?: 0.0);
    }
}
