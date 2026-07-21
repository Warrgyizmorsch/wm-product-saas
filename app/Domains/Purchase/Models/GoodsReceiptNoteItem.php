<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptNoteItem extends BaseModel
{
    use HasFactory;

    protected $table = 'goods_receipt_note_items';

    protected $fillable = [
        'tenant_id',
        'goods_receipt_note_id',
        'purchase_order_item_id',
        'product_id',
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
        'ordered_qty' => 'float',
        'previous_received_qty' => 'float',
        'received_qty' => 'float',
        'accepted_qty' => 'float',
        'rejected_qty' => 'float',
        'remaining_qty' => 'float',
        'unit_rate' => 'float',
        'total_amount' => 'float',
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
}
