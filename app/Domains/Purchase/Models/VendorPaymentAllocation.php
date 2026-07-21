<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPaymentAllocation extends BaseModel
{
    use HasFactory;

    protected $table = 'vendor_payment_allocations';

    protected $fillable = [
        'tenant_id',
        'vendor_payment_id',
        'vendor_bill_id',
        'allocated_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(VendorPayment::class, 'vendor_payment_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }
}
