<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorBill extends BaseModel
{
    use BelongsToCompany, BelongsToBranch, HasFactory;

    /** Held by 3-way match: not in the ledger and not payable until released. */
    public const STATUS_ON_HOLD = 'On Hold';

    public const MATCH_MATCHED = 'matched';
    public const MATCH_EXCEPTION = 'exception';
    public const MATCH_NOT_APPLICABLE = 'not_applicable';

    protected $table = 'vendor_bills';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'bill_number',
        'vendor_invoice_number',
        'vendor_bill_number',
        'discount_type',
        'tax_type',
        'gst_type',
        'freight_terms',
        'freight_amount',
        'freight_tax_method',
        'freight_allocation_method',
        'landed_cost_revaluation_data',
        'discount_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'goods_receipt_note_id',
        'dispatch_order_id',
        'purchase_order_id',
        'vendor_id',
        'bill_date',
        'due_date',
        'status', // Draft, Posted, Partially Paid, Paid, Cancelled, On Hold
        'match_status',
        'match_details',
        'match_checked_at',
        'hold_released_by',
        'hold_released_at',
        'hold_release_reason',
        'subtotal',
        'tax_amount',
        'adjustment',
        'grand_total',
        'total_amount',
        'paid_amount',
        'due_amount',
        'tds_section_id',
        'tds_base_amount',
        'tds_rate',
        'tds_amount',
        'tds_catchup_base',
        'tds_reason',
        'tds_overridden',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'match_details' => 'array',
        'match_checked_at' => 'datetime',
        'hold_released_at' => 'datetime',
        'bill_date' => 'date',
        'due_date' => 'date',
        'freight_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'cgst_amount' => 'decimal:4',
        'sgst_amount' => 'decimal:4',
        'igst_amount' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'due_amount' => 'decimal:4',
        'tds_base_amount' => 'decimal:4',
        'tds_rate' => 'float',
        'tds_amount' => 'decimal:4',
        'tds_overridden' => 'boolean',
        'landed_cost_revaluation_data' => 'array',
    ];

    protected static function booted(): void
    {
        // TDS is worked out once, when the bill is made (see VendorBillTdsService).
        static::creating(fn (VendorBill $bill) => app(\App\Domains\Purchase\Services\VendorBillTdsService::class)->apply($bill));
    }

    /**
     * What the vendor is owed for this bill in total: the bill less the TDS
     * withheld. Paid + due always adds up to this, never to grand_total.
     */
    public function netPayable(): float
    {
        return round((float) ($this->grand_total ?: ($this->attributes['total_amount'] ?? 0)) - (float) ($this->tds_amount ?? 0), 4);
    }

    /** What is still due once $paid has been paid against the bill in total. */
    public function dueAfterPaying(float $paid): float
    {
        return max(0.0, round($this->netPayable() - $paid, 4));
    }

    public function tdsSection(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Accounting\Models\TdsSection::class, 'tds_section_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorBillItem::class, 'vendor_bill_id');
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(VendorPayment::class, 'vendor_payment_allocations', 'vendor_bill_id', 'vendor_payment_id')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class, 'vendor_bill_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'goods_receipt_note_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function dispatchOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Sales\Models\DispatchOrder::class, 'dispatch_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function holdReleasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hold_released_by')->withoutGlobalScope('tenant');
    }

    public function isOnHold(): bool
    {
        return $this->status === self::STATUS_ON_HOLD;
    }

    public function getTotalAmountAttribute(): float
    {
        return (float)($this->attributes['grand_total'] ?? $this->attributes['total_amount'] ?? 0);
    }

    public function getAmountPaidAttribute(): float
    {
        return (float)($this->attributes['paid_amount'] ?? $this->attributes['amount_paid'] ?? 0);
    }

    public function getBalanceDueAttribute(): float
    {
        $total = $this->total_amount;
        $paid = $this->amount_paid;
        return (float)($this->attributes['due_amount'] ?? max(0, $total - $paid));
    }
}
