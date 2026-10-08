<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionMaintenanceWorkOrder extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

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

    // Legacy mechanic-type constants kept for backwards-compat with existing log records / test fixtures.
    public const MECHANIC_TYPE_INHOUSE = 'inhouse';
    public const MECHANIC_TYPE_EXTERNAL = 'external';
    public const MECHANIC_TYPE_BOTH = 'both';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
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
        'mechanic_cost',
        'spare_parts_cost',
        'additional_cost',
        'external_parts_purchased',
        'was_machine_scraped',
        'decision_note',
        'total_cost',
        'downtime_id',
        'status',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'planned_start'            => 'datetime',
        'planned_end'              => 'datetime',
        'actual_start'             => 'datetime',
        'actual_end'               => 'datetime',
        'checklist_json'           => 'array',
        'mechanic_cost'            => 'decimal:2',
        'spare_parts_cost'         => 'decimal:2',
        'additional_cost'          => 'decimal:2',
        'external_parts_purchased' => 'boolean',
        'was_machine_scraped'      => 'boolean',
        'total_cost'               => 'decimal:2',
    ];

    public function getWasMachineScrapedAttribute($value): bool
    {
        return (bool) ($value ?? false);
    }

    public function getMechanicCostAttribute($value): float
    {
        return (float) ($value ?? 0.0);
    }

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

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductionMaintenanceWorkOrderAssignment::class, 'work_order_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProductionMaintenanceWorkOrderLog::class, 'work_order_id');
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
