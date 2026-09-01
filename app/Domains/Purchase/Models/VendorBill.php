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
        'discount_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'goods_receipt_note_id',
        'purchase_order_id',
        'vendor_id',
        'bill_date',
        'due_date',
        'status', // Draft, Posted, Partially Paid, Paid, Cancelled
        'subtotal',
        'tax_amount',
        'grand_total',
        'total_amount',
        'paid_amount',
        'due_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'freight_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalAmountAttribute(): float
    {
        return (float)($this->attributes['grand_total'] ?? $this->attributes['total_amount'] ?? 0);
    }
}
