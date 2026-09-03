<?php

namespace App\Domains\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;

class PurchaseOrderItem extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'company_id',
        'branch_id',
        'product_id',
        'line_type',
        'chart_of_account_id',
        'asset_category_id',
        'production_order_id',
        'production_order_operation_id',
        'production_batch_id',
        'requisition_item_allocations',
        'quantity',
        'received_qty',
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

    public const LINE_TYPE_STOCK = 'stock';
    public const LINE_TYPE_ASSET = 'asset';
    public const LINE_TYPE_EXPENSE = 'expense';

    public const LINE_TYPES = [
        self::LINE_TYPE_STOCK,
        self::LINE_TYPE_ASSET,
        self::LINE_TYPE_EXPENSE,
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'product_id' => 'integer',
        'chart_of_account_id' => 'integer',
        'asset_category_id' => 'integer',
        'production_order_id' => 'integer',
        'production_order_operation_id' => 'integer',
        'production_batch_id' => 'integer',
        'requisition_item_allocations' => 'array',
        'quantity' => 'decimal:4',
        'received_qty' => 'decimal:4',
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

    public function getRemainingQtyAttribute(): float
    {
        return max(0.0, (float)$this->quantity - (float)($this->received_qty ?? 0));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\AssetCategory::class, 'asset_category_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Accounting\Models\ChartOfAccount::class, 'chart_of_account_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Production\Models\ProductionOrder::class, 'production_order_id');
    }

    public function productionOrderOperation(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Production\Models\ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Production\Models\ProductionBatch::class, 'production_batch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault(function () {
            return $this->order?->warehouse;
        });
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->tenant_id) && $item->order) {
                $item->tenant_id = $item->order->tenant_id;
            }
            if (empty($item->company_id) && $item->order) {
                $item->company_id = $item->order->company_id;
            }
            if (empty($item->branch_id) && $item->order) {
                $item->branch_id = $item->order->branch_id;
            }
        });
    }
}
