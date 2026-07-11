<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\CRM\Models\Customer;

class CustomerPayment extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'reference_no',
        'status',
        'notes'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
