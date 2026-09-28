<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\InventoryRemnant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderRemnantAllocation extends BaseModel
{
    protected $table = 'production_order_remnant_allocations';

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'production_order_reservation_id',
        'remnant_id',
        'allocated_quantity',
        'allocated_length',
        'status',
        'reserved_at',
        'released_at',
        'consumed_at',
    ];

    protected $casts = [
        'allocated_quantity' => 'float',
        'allocated_length' => 'float',
        'reserved_at' => 'datetime',
        'released_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderReservation::class, 'production_order_reservation_id');
    }

    public function remnant(): BelongsTo
    {
        return $this->belongsTo(InventoryRemnant::class, 'remnant_id');
    }
}
