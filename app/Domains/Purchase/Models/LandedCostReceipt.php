<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostReceipt extends BaseModel
{
    use HasFactory;

    protected $table = 'landed_cost_receipts';

    protected $fillable = [
        'tenant_id',
        'landed_cost_voucher_id',
        'goods_receipt_note_id',
    ];

    public function landedCostVoucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class, 'landed_cost_voucher_id');
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'goods_receipt_note_id');
    }
}
