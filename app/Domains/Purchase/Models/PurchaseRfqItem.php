<?php

namespace App\Domains\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\Concerns\BelongsToTenant;

class PurchaseRfqItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'purchase_rfq_items';

    protected $fillable = [
        'purchase_rfq_id',
        'product_id',
        'quantity',
        'estimated_cost',
    ];

    protected $casts = [
        'purchase_rfq_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'decimal:4',
        'estimated_cost' => 'decimal:2',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfq::class, 'purchase_rfq_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function vendors(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Inventory\Models\Vendor::class, 'purchase_rfq_item_vendors', 'purchase_rfq_item_id', 'vendor_id')
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->tenant_id) && $item->rfq) {
                $item->tenant_id = $item->rfq->tenant_id;
            }
        });
    }
}
