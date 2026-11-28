<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One receipt of outsourced work (SC-02: Subcontracting menu or shop floor; TF-06). It books the accepted quantity on
 * the operation and creates the stock-free system GRN that keeps the subcontract PO and the vendor bill in step (SC-03).
 */
class ProductionSubcontractReceipt extends BaseModel
{
    public const SOURCE_CHALLAN = 'challan';
    public const SOURCE_SHOP_FLOOR = 'shop_floor';

    public const STATUS_BOOKED = 'booked';           // quantities on the operation, system GRN created
    public const STATUS_QC_PENDING = 'qc_pending';   // waiting for the operation's QC; booked when QC is recorded
    public const STATUS_REJECTED_IN_QC = 'rejected_in_qc';

    protected $table = 'production_subcontract_receipts';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'receipt_number',
        'production_order_id',
        'production_order_operation_id',
        'delivery_challan_id',

        'goods_receipt_note_id',
        'source',
        'status',
        'received_qty',
        'accepted_qty',
        'rejected_qty',
        'scrapped_qty',
        'remarks',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'received_qty' => 'float',
        'accepted_qty' => 'float',
        'rejected_qty' => 'float',
        'scrapped_qty' => 'float',
        'received_at' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function challan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'delivery_challan_id');
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'goods_receipt_note_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
