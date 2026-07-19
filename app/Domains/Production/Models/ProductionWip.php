<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionWip extends BaseModel
{
    protected $table = 'production_wips';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'production_batch_id',
        'product_id',
        'current_routing_operation_id',
        'current_schedule_operation_id',
        'current_work_center_id',
        'current_machine_id',
        'quantity',
        'available_quantity',
        'completed_quantity',
        'rejected_quantity',
        'scrap_quantity',
        'rework_quantity',
        'status',
        'material_cost',
        'labor_cost',
        'machine_cost',
        'overhead_cost',
        'total_value',
        'started_at',
        'last_moved_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'float',
        'available_quantity' => 'float',
        'completed_quantity' => 'float',
        'rejected_quantity' => 'float',
        'scrap_quantity' => 'float',
        'rework_quantity' => 'float',
        'material_cost' => 'float',
        'labor_cost' => 'float',
        'machine_cost' => 'float',
        'overhead_cost' => 'float',
        'total_value' => 'float',
        'started_at' => 'datetime',
        'last_moved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id')->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function currentRoutingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'current_routing_operation_id');
    }

    public function currentScheduleOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionScheduleOperation::class, 'current_schedule_operation_id');
    }

    public function currentWorkCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'current_work_center_id');
    }

    public function currentMachine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'current_machine_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ProductionWipTransaction::class, 'wip_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
