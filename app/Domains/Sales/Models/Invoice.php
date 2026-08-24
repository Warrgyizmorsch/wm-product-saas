<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Domains\CRM\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'sales_order_id',
        'material_requirement_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'subtotal',
        'tax_amount',
        'gst_type',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'balance_due',
        'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function materialRequirement(): BelongsTo
    {
        return $this->belongsTo(MaterialRequirement::class, 'material_requirement_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
