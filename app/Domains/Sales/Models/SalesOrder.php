<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Purchase\Models\PurchaseRequisition;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

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
        'company_id',
        'branch_id',
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
        'discount_type',
        'tax_mode',
        'tax_type',
        'gst_type',
        'order_tax_rate',
        'subtotal',
        'tax',
        'discount',
        'freight_terms',
        'freight_amount',
        'freight_tax_rate',
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
        'freight_amount'   => 'decimal:2',
        'shipping_charges' => 'decimal:2',
        'adjustment'       => 'decimal:2',
        'total_amount'     => 'decimal:2',
    ];

    public function getEffectiveTaxModeAttribute(): string
    {
        return !empty($this->tax_mode) ? $this->tax_mode : 'exclusive';
    }

    public function getTaxableAmountAttribute(): float
    {
        $subtotal = (float) ($this->subtotal ?? 0);
        $discount = (float) ($this->discount ?? 0);
        $tax = (float) ($this->tax ?? 0);

        if ($this->effective_tax_mode === 'inclusive') {
            return round(max(0, ($subtotal - $discount) - $tax), 2);
        }

        return round(max(0, $subtotal - $discount), 2);
    }

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
        return $this->belongsTo(User::class, 'sales_person_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function materialRequirements(): HasMany
    {
        return $this->hasMany(MaterialRequirement::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(DispatchOrder::class, 'sales_order_id');
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
        return $this->hasMany(ProductionOrder::class, 'sales_order_id');
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'sales_order_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(\App\Domains\Projects\Models\Project::class, 'sales_order_id');
    }

    public function hasOnlyServices(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }
        return $this->items->every(fn($item) => $item->isService());
    }

    public function hasServices(): bool
    {
        return $this->items->contains(fn($item) => $item->isService());
    }

    public function hasPhysicalGoods(): bool
    {
        return $this->items->contains(fn($item) => !$item->isService());
    }

    public function requiresMaterialRequirement(): bool
    {
        return $this->hasPhysicalGoods();
    }

    public function requiresDispatch(): bool
    {
        return $this->hasPhysicalGoods();
    }
}
