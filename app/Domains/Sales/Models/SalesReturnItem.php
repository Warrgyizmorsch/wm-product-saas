<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_id',
        'delivery_order_item_id',
        'invoice_item_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_price'
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
