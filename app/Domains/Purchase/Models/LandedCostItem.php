<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostItem extends BaseModel
{
    use HasFactory;

    protected $table = 'landed_cost_items';

    protected $fillable = [
        'tenant_id',
        'landed_cost_voucher_id',
        'goods_receipt_note_id',
        'goods_receipt_note_item_id',
        'product_id',
        'quantity',
        'base_unit_rate',
        'base_total_amount',
        'allocated_cost',
        'new_landed_unit_cost',
        'new_total_amount',
    ];

    protected $casts = [
        'quantity' => 'float',
        'base_unit_rate' => 'float',
        'base_total_amount' => 'float',
        'allocated_cost' => 'float',
        'new_landed_unit_cost' => 'float',
        'new_total_amount' => 'float',
    ];

    public function landedCostVoucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class, 'landed_cost_voucher_id');
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'goods_receipt_note_id');
    }

    public function goodsReceiptNoteItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNoteItem::class, 'goods_receipt_note_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
