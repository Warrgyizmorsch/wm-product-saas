<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionMaintenanceWorkOrder extends BaseModel
{
    use HasFactory;

    protected $table = 'production_maintenance_work_orders';

    public const TYPE_PREVENTIVE  = 'preventive';
    public const TYPE_BREAKDOWN   = 'breakdown';
    public const TYPE_CALIBRATION = 'calibration';

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_SCHEDULED   = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    public const PRIORITY_LOW      = 'low';
    public const PRIORITY_MEDIUM   = 'medium';
    public const PRIORITY_HIGH     = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'tenant_id',
        'work_order_number',
        'machine_id',
        'pm_schedule_id',
        'type',
        'priority',
        'assigned_technician_id',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'problem_description',
        'work_performed',
        'checklist_json',
        'labor_hours',
        'labor_cost_rate',
        'labor_cost',
        'spare_parts_cost',
        'total_cost',
        'downtime_id',
        'status',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'planned_start'    => 'datetime',
        'planned_end'      => 'datetime',
        'actual_start'     => 'datetime',
        'actual_end'       => 'datetime',
        'checklist_json'   => 'array',
        'labor_hours'      => 'decimal:2',
        'labor_cost_rate'  => 'decimal:2',
        'labor_cost'       => 'decimal:2',
        'spare_parts_cost' => 'decimal:2',
        'total_cost'       => 'decimal:2',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function pmSchedule(): BelongsTo
    {
        return $this->belongsTo(ProductionPmSchedule::class, 'pm_schedule_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function downtime(): BelongsTo
    {
        return $this->belongsTo(ProductionMachineDowntime::class, 'downtime_id');
    }

    public function spares(): HasMany
    {
        return $this->hasMany(ProductionMaintenanceWorkOrderSpare::class, 'maintenance_work_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
