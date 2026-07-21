<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorBillItem extends BaseModel
{
    use HasFactory;

    protected $table = 'vendor_bill_items';

    protected $fillable = [
        'tenant_id',
        'vendor_bill_id',
        'product_id',
        'goods_receipt_note_item_id',
        'quantity',
        'unit_rate',
        'tax_percentage',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_rate' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNoteItem::class, 'goods_receipt_note_item_id');
    }
}
