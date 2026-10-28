<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;

class SalesOrderItem extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'sales_order_items';

    protected $fillable = [
        'company_id',
        'branch_id',
        'sales_order_id',
        'product_id',
        'warehouse_id',
        'item_name',
        'description',
        'quantity',
        'allocated_qty',
        'dispatched_qty',
        'invoiced_qty',
        'cancelled_qty',
        'status',
        'unit_price',
        'tax_rate',
        'discount',
        'amount',
    ];

    protected $casts = [
        'product_id'     => 'integer',
        'warehouse_id'   => 'integer',
        'quantity'       => 'decimal:4',
        'allocated_qty'  => 'decimal:4',
        'dispatched_qty' => 'decimal:4',
        'invoiced_qty'   => 'decimal:4',
        'cancelled_qty'  => 'decimal:4',
        'unit_price'     => 'decimal:2',
        'tax_rate'       => 'decimal:2',
        'discount'       => 'decimal:2',
        'amount'         => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function dispatchItems(): HasMany
    {
        return $this->hasMany(DispatchOrderItem::class, 'sales_order_item_id');
    }

    public function getRemainingQtyAttribute(): float
    {
        return max(0.0, (float) $this->quantity - ((float) $this->dispatched_qty + (float) $this->cancelled_qty));
    }

    public function getOpenAllocatableQtyAttribute(): float
    {
        return max(0.0, (float) $this->quantity - ((float) $this->dispatched_qty + (float) $this->allocated_qty + (float) $this->cancelled_qty));
    }

    public function refreshStatus(): string
    {
        $ordered = (float) $this->quantity;
        $cancelled = (float) $this->cancelled_qty;
        $dispatched = (float) $this->dispatched_qty;
        $allocated = (float) $this->allocated_qty;

        if ($this->isService()) {
            if ($cancelled >= $ordered && $ordered > 0) {
                $newStatus = 'Cancelled';
            } else {
                $invoicedQty = (float) \App\Domains\Sales\Models\InvoiceItem::where('sales_order_item_id', $this->id)
                    ->whereHas('invoice', fn($q) => $q->where('status', '!=', 'Cancelled'))
                    ->sum('quantity');
                if ($invoicedQty >= $ordered && $ordered > 0) {
                    $newStatus = 'Fulfilled';
                } elseif ($invoicedQty > 0) {
                    $newStatus = 'Partially Invoiced';
                } else {
                    $newStatus = ($this->salesOrder?->status === 'Draft') ? 'Pending' : 'Confirmed';
                }
            }

            if ($this->status !== $newStatus) {
                $this->update(['status' => $newStatus]);
            }

            return $newStatus;
        }

        if ($ordered <= 0) {
            $newStatus = 'Completed';
        } elseif ($cancelled >= $ordered) {
            $newStatus = 'Cancelled';
        } elseif (($dispatched + $cancelled) >= $ordered) {
            $newStatus = $cancelled > 0 ? 'Short-Closed' : 'Fulfilled';
        } elseif ($dispatched > 0) {
            $newStatus = 'Partially Dispatched';
        } elseif ($allocated > 0) {
            $newStatus = 'Allocated';
        } else {
            $newStatus = 'Pending';
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }

        return $newStatus;
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(\App\Domains\Projects\Models\Milestone::class, 'sales_order_item_id');
    }

    public function isService(): bool
    {
        return $this->product?->isService() ?? false;
    }

    public function isGoods(): bool
    {
        return !$this->isService();
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->tenant_id) && $item->salesOrder) {
                $item->tenant_id = $item->salesOrder->tenant_id;
            }
            if (empty($item->company_id) && $item->salesOrder) {
                $item->company_id = $item->salesOrder->company_id;
            }
            if (empty($item->branch_id) && $item->salesOrder) {
                $item->branch_id = $item->salesOrder->branch_id;
            }
        });
    }
}
