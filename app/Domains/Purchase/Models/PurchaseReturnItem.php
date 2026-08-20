<?php

namespace App\Domains\Purchase\Models;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'purchase_return_id',
        'goods_receipt_note_item_id',
        'vendor_bill_item_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'total_amount',
        'serial_numbers',
    ];

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->tenant_id) && $item->purchaseReturn) {
                $item->tenant_id = $item->purchaseReturn->tenant_id;
            }
        });
    }
}
