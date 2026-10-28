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
        'closure_reason',
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

    public function isFullyInvoiced(): bool
    {
        $activeInvoices = $this->invoices()->where('status', '!=', 'Cancelled')->get();
        if ($activeInvoices->isEmpty()) {
            return false;
        }

        $orderTotal = (float) ($this->total_amount ?? 0);
        $invoicedAmount = (float) $activeInvoices->sum(fn($i) => (float)($i->total_amount ?? $i->grand_total ?? 0));

        if ($orderTotal > 0 && ($invoicedAmount + 0.01) >= $orderTotal) {
            return true;
        }

        $items = $this->items()->get();
        if ($items->isEmpty()) {
            return false;
        }

        $totalOrdered = (float) $items->sum('quantity');
        if ($totalOrdered <= 0) {
            return false;
        }

        $totalInvoicedQty = (float) \App\Domains\Sales\Models\InvoiceItem::whereHas('invoice', function ($q) {
            $q->where('sales_order_id', $this->id)->where('status', '!=', 'Cancelled');
        })->sum('quantity');

        $totalCancelled = (float) $items->sum('cancelled_qty');

        return ($totalInvoicedQty + $totalCancelled + 0.0001) >= $totalOrdered;
    }

    public function refreshFulfillmentStatus(): string
    {
        if (in_array($this->status, ['Draft', 'Cancelled', 'Closed', 'Short-Closed'])) {
            return $this->status;
        }

        $physicalItems = $this->items()->get()->filter(fn($item) => !$item->isService());
        if ($physicalItems->isEmpty()) {
            $newStatus = $this->isFullyInvoiced() ? 'Completed' : 'Confirmed';
            if ($this->status !== $newStatus) {
                $this->update(['status' => $newStatus]);
            }
            return $newStatus;
        }

        $totalOrdered = (float) $physicalItems->sum('quantity');
        $totalDispatched = (float) $physicalItems->sum('dispatched_qty');
        $totalCancelled = (float) $physicalItems->sum('cancelled_qty');
        $totalAllocated = (float) $physicalItems->sum('allocated_qty');

        if ($totalCancelled >= $totalOrdered && $totalOrdered > 0) {
            $newStatus = 'Cancelled';
        } elseif (($totalDispatched + $totalCancelled) >= $totalOrdered && $totalOrdered > 0) {
            if ($totalCancelled > 0) {
                $newStatus = 'Short-Closed';
            } elseif ($this->isFullyInvoiced()) {
                $newStatus = 'Completed';
            } else {
                $newStatus = 'Shipped';
            }
        } elseif ($totalDispatched > 0) {
            $newStatus = 'Partially Shipped';
        } elseif ($totalAllocated > 0) {
            $newStatus = in_array($this->status, ['In Fulfillment', 'Processing']) ? $this->status : 'In Fulfillment';
        } else {
            $newStatus = 'Confirmed';
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }

        return $newStatus;
    }
}
