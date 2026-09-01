<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;

class SalesOrderItem extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'sales_order_items';

    protected $fillable = [
        'company_id',
        'branch_id',
        'sales_order_id',
        'product_id',
        'warehouse_id',
        'item_name',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'discount',
        'amount',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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
            if (empty($item->tenant_id) && $item->salesOrder) {
                $item->tenant_id = $item->salesOrder->tenant_id;
            }
            if (empty($item->company_id) && $item->salesOrder) {
                $item->company_id = $item->salesOrder->company_id;
            }
            if (empty($item->branch_id) && $item->salesOrder) {
                $item->branch_id = $item->salesOrder->branch_id;
            }
        });
    }
}
