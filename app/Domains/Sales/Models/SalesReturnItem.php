<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;

class SalesReturnItem extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch;

    protected $fillable = [
        'company_id',
        'branch_id',
        'sales_return_id',
        'material_requirement_item_id',
        'invoice_item_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'total_amount',
        'serial_numbers'
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

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->tenant_id) && $item->salesReturn) {
                $item->tenant_id = $item->salesReturn->tenant_id;
            }
            if (empty($item->company_id) && $item->salesReturn) {
                $item->company_id = $item->salesReturn->company_id;
            }
            if (empty($item->branch_id) && $item->salesReturn) {
                $item->branch_id = $item->salesReturn->branch_id;
            }
        });
    }
}
