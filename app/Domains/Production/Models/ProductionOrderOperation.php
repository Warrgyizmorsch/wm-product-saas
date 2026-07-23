<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrderOperation extends BaseModel
{
    protected $table = 'production_order_operations';

    public const STATUS_WAITING   = 'waiting';
    public const STATUS_READY     = 'ready';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED   = 'skipped';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_READY,
        self::STATUS_RUNNING,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
        self::STATUS_SKIPPED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'routing_operation_id',
        'previous_operation_id',
        'sequence',
        'operation_number',
        'name',
        'work_center_id',
        'machine_id',
        'status',
        'setup_time_planned',
        'processing_time_planned',
        'total_time_planned',
        'setup_time_actual',
        'processing_time_actual',
        'actual_start_time',
        'actual_end_time',
        'quantity_produced',
        'quantity_rejected',
        'quantity_scrapped',
        'machine_used_id',
        'operator_id',
        'parallel_group',
        'is_parallel',
        'parallel_type',
    ];

    protected $casts = [
        'sequence'                => 'integer',
        'setup_time_planned'      => 'float',
        'processing_time_planned' => 'float',
        'total_time_planned'      => 'float',
        'setup_time_actual'       => 'float',
        'processing_time_actual'  => 'float',
        'actual_start_time'       => 'datetime',
        'actual_end_time'         => 'datetime',
        'quantity_produced'       => 'float',
        'quantity_rejected'       => 'float',
        'quantity_scrapped'       => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'routing_operation_id');
    }

    public function previousOperation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_operation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function machineUsed(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_used_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ProductionOrderProgressLog::class, 'operation_id');
    }

    public function scheduleOperation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductionScheduleOperation::class, 'production_order_operation_id');
    }

    public function operatorAssignments(): HasMany
    {
        return $this->hasMany(ProductionOperatorAssignment::class, 'production_order_operation_id');
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
