<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends BaseModel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'sales_orders';

    protected function salesOrderNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => 'SO-' . $value,
            set: fn ($value) => str_replace('SO-', '', $value),
        );
    }

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'quotation_id',
        'sales_order_number',
        'order_date',
        'shipment_date',
        'status',
        'billing_address',
        'shipping_address',
        'payment_terms',
        'sales_person_id',
        'subtotal',
        'tax',
        'discount',
        'shipping_charges',
        'adjustment',
        'total_amount',
        'terms_conditions',
        'notes',
    ];

    protected $casts = [
        'order_date'       => 'date',
        'shipment_date'    => 'date',
        'subtotal'         => 'decimal:2',
        'tax'              => 'decimal:2',
        'discount'         => 'decimal:2',
        'shipping_charges' => 'decimal:2',
        'adjustment'       => 'decimal:2',
        'total_amount'     => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sales_person_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(\App\Domains\Production\Models\ProductionOrder::class, 'sales_order_id');
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\PurchaseRequisition::class, 'sales_order_id');
    }

}
