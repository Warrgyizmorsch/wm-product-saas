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
        'source_product_id',
        'source_bom_id',
        'source_routing_id',
        'bom_level',
        'target_produced_qty',
        'is_intermediate',
        'quantity_claimed',
        'quantity_consumed',
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
        'is_external',
        'vendor_id',
        'subcontract_lead_time_days',
        'subcontract_cost_per_unit',
        'subcontract_service_product_id',
        'material_supply_type',
        'dispatch_buffer_days',
        'return_buffer_days',
        'purchase_order_id',
        'purchase_order_item_id',
        'quality_required',
        'parallel_group',
        'is_parallel',
        'parallel_type',
        'queue_threshold_enabled',
        'overlap_enabled',
        'transfer_batch_quantity',
        'transfer_lag_minutes',
        'quantity_transferred_out',
        'quantity_transferred_in',
    ];

    protected $casts = [
        'sequence'                => 'integer',
        'bom_level'               => 'integer',
        'target_produced_qty'     => 'float',
        'is_intermediate'         => 'boolean',
        'quantity_claimed'        => 'float',
        'quantity_consumed'       => 'float',
        'setup_time_planned'      => 'float',
        'processing_time_planned' => 'float',
        'total_time_planned'      => 'float',
        'setup_time_actual'       => 'float',
        'processing_time_actual'  => 'float',
        'is_external'             => 'boolean',
        'quality_required'        => 'boolean',
        'subcontract_lead_time_days'=> 'integer',
        'subcontract_cost_per_unit' => 'float',
        'dispatch_buffer_days'      => 'integer',
        'return_buffer_days'        => 'integer',
        'quantity_produced'       => 'float',
        'quantity_rejected'       => 'float',
        'quantity_scrapped'       => 'float',
        'actual_start_time'       => 'datetime',
        'actual_end_time'         => 'datetime',
        'queue_threshold_enabled' => 'boolean',
        'overlap_enabled'         => 'boolean',
        'transfer_batch_quantity' => 'float',
        'transfer_lag_minutes'    => 'integer',
        'quantity_transferred_out'=> 'float',
        'quantity_transferred_in' => 'float',
    ];

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Product::class, 'source_product_id');
    }

    public function sourceBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'source_bom_id');
    }

    public function sourceRouting(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'source_routing_id');
    }

    public function predecessorDependencies(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'production_order_operation_dependencies',
            'operation_id',
            'predecessor_operation_id'
        )->withPivot('dependency_type')->withTimestamps();
    }

    public function successorDependencies(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'production_order_operation_dependencies',
            'predecessor_operation_id',
            'operation_id'
        )->withPivot('dependency_type')->withTimestamps();
    }

    public function getOverlapEnabledAttribute(): bool
    {
        return (bool) ($this->attributes['queue_threshold_enabled'] ?? $this->attributes['overlap_enabled'] ?? false);
    }

    public function setOverlapEnabledAttribute($value): void
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->attributes['queue_threshold_enabled'] = $bool;
        if (array_key_exists('overlap_enabled', $this->attributes)) {
            $this->attributes['overlap_enabled'] = $bool;
        }
    }

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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Vendor::class, 'vendor_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Purchase\Models\PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Purchase\Models\PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function subcontractServiceProduct(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Product::class, 'subcontract_service_product_id');
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

    public function isOutsourced(): bool
    {
        return (bool) ($this->is_external || $this->routingOperation?->isOutsourced() || $this->purchase_order_id !== null);
    }
}
