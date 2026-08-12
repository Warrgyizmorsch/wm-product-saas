<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionScheduleScenarioOperation extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'production_schedule_scenario_operations';

    protected $fillable = [
        'tenant_id',
        'scenario_id',
        'source_schedule_operation_id',
        'production_schedule_id',
        'production_order_id',
        'production_order_operation_id',
        'work_center_id',
        'machine_id',
        'sequence',
        'priority',
        'planned_start',
        'planned_finish',
        'planned_duration_minutes',
        'status',
        'locked',
        'manual_override',
        'source_version',
        'scenario_metadata',
    ];

    protected $casts = [
        'planned_start'            => 'datetime',
        'planned_finish'           => 'datetime',
        'planned_duration_minutes' => 'float',
        'sequence'                 => 'integer',
        'priority'                 => 'integer',
        'source_version'           => 'integer',
        'locked'                   => 'boolean',
        'manual_override'          => 'boolean',
        'scenario_metadata'        => 'array',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ProductionScheduleScenario::class, 'scenario_id');
    }

    public function sourceOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionScheduleOperation::class, 'source_schedule_operation_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class, 'production_schedule_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function orderOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
