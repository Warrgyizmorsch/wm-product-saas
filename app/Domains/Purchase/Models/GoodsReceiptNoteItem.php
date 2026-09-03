<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptNoteItem extends BaseModel
{
    use BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'goods_receipt_note_items';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'goods_receipt_note_id',
        'purchase_order_item_id',
        'product_id',
        'line_type',
        'chart_of_account_id',
        'asset_category_id',
        'production_order_id',
        'production_order_operation_id',
        'production_batch_id',
        'ordered_qty',
        'previous_received_qty',
        'received_qty',
        'accepted_qty',
        'rejected_qty',
        'remaining_qty',
        'unit_rate',
        'total_amount',
        'remarks',
    ];

    protected $casts = [
        'chart_of_account_id' => 'integer',
        'asset_category_id' => 'integer',
        'ordered_qty' => 'float',
        'previous_received_qty' => 'float',
        'received_qty' => 'float',
        'accepted_qty' => 'float',
        'rejected_qty' => 'float',
        'remaining_qty' => 'float',
        'unit_rate' => 'float',
        'total_amount' => 'float',
        'production_order_id' => 'integer',
        'production_order_operation_id' => 'integer',
        'production_batch_id' => 'integer',
    ];

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'goods_receipt_note_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
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
}
