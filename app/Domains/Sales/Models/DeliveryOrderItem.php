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
    ];

    protected $casts = [
        'delivery_order_id' => 'integer',
        'sales_order_item_id' => 'integer',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'batch_id' => 'integer',
        'quantity' => 'decimal:4',
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
}
