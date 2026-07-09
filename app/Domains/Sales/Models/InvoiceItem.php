<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'sales_order_item_id',
        'delivery_order_item_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'tax_rate',
        'discount',
        'subtotal'
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
