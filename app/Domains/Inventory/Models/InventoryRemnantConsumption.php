<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRemnantConsumption extends BaseModel
{
    protected $table = 'inventory_remnant_consumptions';

    public const EVENT_CONSUMPTION = 'consumption';
    public const EVENT_SPLIT = 'split';

    protected $fillable = [
        'tenant_id',
        'remnant_id',
        'event_type',
        'production_order_id',
        'production_order_operation_id',
        'split_remnant_id',
        'consumed_quantity',
        'consumed_length',
        'remaining_quantity',
        'remaining_length',
        'unit_cost',
        'total_cost',
        'performed_by',
        'performed_at',
        'notes',
    ];

    protected $casts = [
        'consumed_quantity' => 'float',
        'consumed_length' => 'float',
        'remaining_quantity' => 'float',
        'remaining_length' => 'float',
        'unit_cost' => 'float',
        'total_cost' => 'float',
        'performed_at' => 'datetime',
    ];

    public function isSplit(): bool
    {
        return $this->event_type === self::EVENT_SPLIT;
    }

    public function isConsumption(): bool
    {
        return $this->event_type === self::EVENT_CONSUMPTION;
    }

    public function remnant(): BelongsTo
    {
        return $this->belongsTo(InventoryRemnant::class, 'remnant_id');
    }

    public function splitRemnant(): BelongsTo
    {
        return $this->belongsTo(InventoryRemnant::class, 'split_remnant_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function productionOrderOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
