<?php

namespace App\Domains\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\Concerns\BelongsToTenant;

class PurchaseOrderItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'rate',
        'amount',
        'discount_percent',
        'discount_amount',
        'tax_percent',
        'cgst_percent',
        'sgst_percent',
        'igst_percent',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'tax_amount',
        'total_amount',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'decimal:4',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'cgst_percent' => 'decimal:2',
        'sgst_percent' => 'decimal:2',
        'igst_percent' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->tenant_id) && $item->order) {
                $item->tenant_id = $item->order->tenant_id;
            }
        });
    }
}
