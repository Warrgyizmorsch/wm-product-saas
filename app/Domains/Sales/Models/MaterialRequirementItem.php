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
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Production\Models\ProductionOrder;

class MaterialRequirementItem extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'material_requirement_items';

    protected $fillable = [
        'company_id',
        'branch_id',
        'material_requirement_id',
        'sales_order_item_id',
        'product_id',
        'warehouse_id',
        'batch_id',
        'quantity',
        'quantity_ordered',
        'quantity_reserved',
        'quantity_pr_raised',
        'status',
        'purchase_requisition_id',
        'production_order_id',
    ];

    protected $casts = [
        'material_requirement_id' => 'integer',
        'sales_order_item_id' => 'integer',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'batch_id' => 'integer',
        'quantity' => 'decimal:4',
        'quantity_ordered' => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'quantity_pr_raised' => 'decimal:4',
        'purchase_requisition_id' => 'integer',
        'production_order_id' => 'integer',
    ];

    public function materialRequirement(): BelongsTo
    {
        return $this->belongsTo(MaterialRequirement::class, 'material_requirement_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'material_requirement_item_id');
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function dispatchItems(): HasMany
    {
        return $this->hasMany(DispatchOrderItem::class, 'material_requirement_item_id');
    }

    public function getDispatchedQtyAttribute(): float
    {
        return (float) $this->dispatchItems()
            ->whereHas('dispatchOrder', function ($q) {
                $q->where('status', '!=', 'Cancelled');
            })
            ->sum('quantity_dispatched');
    }

    public function getPendingQtyAttribute(): float
    {
        $ordered = (float) ($this->quantity_ordered > 0 ? $this->quantity_ordered : $this->quantity);
        return max(0.0, $ordered - $this->dispatched_qty - (float) $this->quantity_reserved);
    }

    public function getRemainingPrQtyAttribute(): float
    {
        $prRaised = (float) ($this->quantity_pr_raised ?? 0);
        return max(0.0, $this->pending_qty - $prRaised);
    }

    public function getCalculatedStatusAttribute(): string
    {
        $ordered = (float) ($this->quantity_ordered > 0 ? $this->quantity_ordered : $this->quantity);
        $dispatched = $this->dispatched_qty;
        $reserved = (float) $this->quantity_reserved;

        if ($dispatched >= $ordered) {
            return 'Dispatched';
        } elseif ($dispatched > 0) {
            return 'Partially Dispatched';
        } elseif ($reserved >= $ordered) {
            return 'Reserved';
        } elseif ($reserved > 0) {
            return 'Partially Reserved';
        }

        return $this->status ?: 'Pending';
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->tenant_id) && $item->materialRequirement) {
                $item->tenant_id = $item->materialRequirement->tenant_id;
            }
            if (empty($item->company_id) && $item->materialRequirement) {
                $item->company_id = $item->materialRequirement->company_id;
            }
            if (empty($item->branch_id) && $item->materialRequirement) {
                $item->branch_id = $item->materialRequirement->branch_id;
            }
        });
    }
}
