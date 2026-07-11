<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchOrderItem extends Model
{
    use HasFactory;

    protected $table = 'dispatch_order_items';

    protected $fillable = [
        'dispatch_order_id',
        'delivery_order_item_id',
        'product_id',
        'warehouse_id',
        'quantity_ordered',
        'quantity_dispatched',
    ];

    protected $casts = [
        'quantity_ordered'    => 'decimal:4',
        'quantity_dispatched' => 'decimal:4',
    ];

    public function dispatchOrder(): BelongsTo
    {
        return $this->belongsTo(DispatchOrder::class, 'dispatch_order_id');
    }

    public function deliveryOrderItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderItem::class, 'delivery_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Warehouse::class, 'warehouse_id');
    }
}
