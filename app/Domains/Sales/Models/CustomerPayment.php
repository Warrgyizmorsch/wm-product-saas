<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\CRM\Models\Customer;

class CustomerPayment extends BaseModel
{
    use BelongsToCompany, BelongsToBranch;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'customer_id',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'bank_account_id',
        'reference_no',
        'status',
        'notes',
        // TDS the customer withheld: the invoice is settled by amount + tds_amount.
        'tds_amount',
        'tds_section_code',
        'tds_certificate_received',
    ];

    protected $casts = [
        'tds_amount' => 'float',
        'tds_certificate_received' => 'boolean',
    ];

    /** What this receipt settles on the customer's account: money received plus TDS withheld. */
    public function settledAmount(): float
    {
        return round((float) $this->amount + (float) ($this->tds_amount ?? 0), 2);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
