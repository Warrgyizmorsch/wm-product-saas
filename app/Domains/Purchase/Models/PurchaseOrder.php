<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'tenant_id',
        'purchase_order_number',
        'purchase_requisition_id',
        'source_type',
        'vendor_id',
        'location',
        'reference',
        'supplier_quotation_number',
        'date',
        'delivery_date',
        'discount_type',
        'tax_type',
        'gst_type',
        'subtotal',
        'discount_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'tax_amount',
        'grand_total',
        'status', // Draft, Approved, Cancelled
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'delivery_date' => 'date',
        'purchase_requisition_id' => 'integer',
        'vendor_id' => 'integer',
        'created_by' => 'integer',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function goodsReceiptNotes(): HasMany
    {
        return $this->hasMany(GoodsReceiptNote::class, 'purchase_order_id');
    }

    public function advancePayments(): HasMany
    {
        return $this->hasMany(PurchaseAdvancePayment::class, 'purchase_order_id')->where('status', 'Posted');
    }

    public function getTotalAdvancePaidAttribute(): float
    {
        return (float) $this->advancePayments->sum('amount');
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0.0, (float)$this->grand_total - $this->total_advance_paid);
    }

    public function getOrderedQtyAttribute(): float
    {
        return (float) $this->items->sum('quantity');
    }

    public function getReceivedQtyAttribute(): float
    {
        return (float) $this->items->sum('received_qty');
    }

    public function getRemainingQtyAttribute(): float
    {
        return max(0.0, $this->ordered_qty - $this->received_qty);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Warehouse::class, 'location', 'name');
    }

    public function getSupplierQuotationNumberAttribute(): ?string
    {
        if (!empty($this->attributes['supplier_quotation_number'])) {
            return $this->attributes['supplier_quotation_number'];
        }

        if ($this->reference && preg_match('/Quote (?:No|Ref):\s*([^\s\|]+)/i', $this->reference, $matches)) {
            return trim($matches[1]);
        }

        if ($this->source_type === 'rfq' && $this->reference) {
            if (preg_match('/RFQ:\s*([^\s\|]+)/i', $this->reference, $rfqMatches)) {
                $rfqNumber = trim($rfqMatches[1]);
                $rfq = PurchaseRfq::where('tenant_id', $this->tenant_id)
                    ->where('rfq_number', $rfqNumber)
                    ->first();
                if ($rfq) {
                    $vendorRfq = PurchaseRfqVendor::where('purchase_rfq_id', $rfq->id)
                        ->where('vendor_id', $this->vendor_id)
                        ->first();
                    return $vendorRfq?->quotation_number;
                }
            }
        }

        return null;
    }
}
