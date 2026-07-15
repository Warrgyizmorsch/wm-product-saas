<?php

namespace App\Domains\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Models\SalesOrderItem;
use App\Models\Concerns\BelongsToTenant;

class PurchaseRequisitionItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'purchase_requisition_items';

    protected $fillable = [
        'purchase_requisition_id',
        'sales_order_item_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'estimated_cost',
    ];

    protected $casts = [
        'purchase_requisition_id' => 'integer',
        'sales_order_item_id' => 'integer',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'quantity' => 'decimal:4',
        'estimated_cost' => 'decimal:2',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->tenant_id) && $item->requisition) {
                $item->tenant_id = $item->requisition->tenant_id;
            }
        });
    }
}
