<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends BaseModel
{
    use BelongsToCompany, BelongsToBranch;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'customer_payment_id',
        'sales_order_id',
        'invoice_id',
        'allocated_amount'
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
