<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRemnantAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryRemnant extends BaseModel
{
    use SoftDeletes;

    protected $table = 'inventory_remnants';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_PARTIALLY_RESERVED = 'partially_reserved';
    public const STATUS_FULLY_RESERVED = 'fully_reserved';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_SCRAPPED = 'scrapped';
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

    public const TYPE_LINEAR = 'linear';
    public const TYPE_SHEET = 'sheet';
    public const TYPE_WEIGHT = 'weight';
    public const TYPE_COUNT = 'count';

    protected $fillable = [
        'tenant_id',
        'remnant_code',
        'product_id',
        'warehouse_id',
        'warehouse_location',
        'measurement_type',
        'status',
        'parent_batch_id',
        'heat_number',
        'parent_remnant_id',
        'source_production_order_id',
        'source_production_order_operation_id',
        'dimension_unit',
        'initial_length',
        'current_length',
        'reserved_length',
        'initial_width',
        'current_width',
        'thickness',
        'weight',
        'weight_unit',
        'pieces',
        'uom_id',
        'initial_quantity',
        'current_quantity',
        'reserved_quantity',
        'unit_cost',
        'confirmed_by',
        'confirmed_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'initial_length' => 'float',
        'current_length' => 'float',
        'reserved_length' => 'float',
        'initial_width' => 'float',
        'current_width' => 'float',
        'thickness' => 'float',
        'weight' => 'float',
        'pieces' => 'integer',
        'initial_quantity' => 'float',
        'current_quantity' => 'float',
        'reserved_quantity' => 'float',
        'unit_cost' => 'float',
        'confirmed_at' => 'datetime',
    ];

    public function getAvailableQuantityAttribute(): float
    {
        return max(0.0, (float) $this->current_quantity - (float) ($this->reserved_quantity ?? 0.0));
    }

    public function getAvailableLengthAttribute(): float
    {
        return max(0.0, (float) ($this->current_length ?? 0.0) - (float) ($this->reserved_length ?? 0.0));
    }

    public function getTotalValuationAttribute(): float
    {
        return round((float) $this->current_quantity * (float) $this->unit_cost, 4);
    }

    public function getAgeDaysAttribute(): int
    {
        return $this->created_at ? (int) $this->created_at->diffInDays(now()) : 0;
    }

    public function isAvailable(): bool
    {
        return in_array($this->status, [self::STATUS_AVAILABLE, self::STATUS_PARTIALLY_RESERVED], true)
            && $this->available_quantity > 0;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'parent_batch_id');
    }

    public function parentRemnant(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_remnant_id');
    }

    public function childRemnants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_remnant_id');
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'source_production_order_id');
    }

    public function sourceOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'source_production_order_operation_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(InventoryRemnantConsumption::class, 'remnant_id')->orderBy('performed_at', 'desc');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProductionOrderRemnantAllocation::class, 'remnant_id');
    }

    public function activeAllocations(): HasMany
    {
        return $this->hasMany(ProductionOrderRemnantAllocation::class, 'remnant_id')->where('status', 'reserved');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
