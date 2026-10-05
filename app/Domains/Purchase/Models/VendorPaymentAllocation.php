<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPaymentAllocation extends BaseModel
{
    use BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'vendor_payment_allocations';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'vendor_payment_id',
        'vendor_bill_id',
        'allocated_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
    ];

    /**
     * Backstop for every payment path (web, API, bank reconciliation): money
     * can't be applied to a bill held by 3-way match.
     */
    protected static function booted(): void
    {
        static::creating(function (self $allocation): void {
            $status = VendorBill::query()->withoutGlobalScopes()->whereKey($allocation->vendor_bill_id)->value('status');

            if ($status === VendorBill::STATUS_ON_HOLD) {
                throw new \InvalidArgumentException('This bill is on hold for a PO/GRN mismatch and cannot be paid until it is released.');
            }
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(VendorPayment::class, 'vendor_payment_id');
    }

    // Alias for use in whereHas() queries
    public function vendorPayment(): BelongsTo
    {
        return $this->belongsTo(VendorPayment::class, 'vendor_payment_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }
}
