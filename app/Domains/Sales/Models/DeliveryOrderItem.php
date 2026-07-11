<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrderItem extends Model
{
    use HasFactory;

    protected $table = 'delivery_order_items';

    protected $fillable = [
        'delivery_order_id',
        'sales_order_item_id',
        'product_id',
        'warehouse_id',
        'batch_id',
        'quantity',
        'quantity_ordered',
        'quantity_reserved',
        'status',
        'purchase_requisition_id',
        'production_order_id',
    ];

    protected $casts = [
        'delivery_order_id' => 'integer',
        'sales_order_item_id' => 'integer',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'batch_id' => 'integer',
        'quantity' => 'decimal:4',
        'quantity_ordered' => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'purchase_requisition_id' => 'integer',
        'production_order_id' => 'integer',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Warehouse::class, 'warehouse_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Batch::class, 'batch_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(\App\Domains\Inventory\Models\SerialNumber::class, 'delivery_order_item_id');
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Purchase\Models\PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Production\Models\ProductionOrder::class, 'production_order_id');
    }

    public function dispatchItems(): HasMany
    {
        return $this->hasMany(DispatchOrderItem::class, 'delivery_order_item_id');
    }
}
