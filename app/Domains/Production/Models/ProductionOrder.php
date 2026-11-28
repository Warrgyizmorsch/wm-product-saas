<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Loggable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends BaseModel
{
    use HasFactory, Loggable, SoftDeletes, BelongsToCompany, BelongsToBranch;

    protected $table = 'production_orders';

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($order) {
            if (in_array($order->status, [self::STATUS_COMPLETED, self::STATUS_CLOSED])) {
                // Find and update linked ProductionOrderRequests
                $requests = ProductionOrderRequest::where('tenant_id', $order->tenant_id)
                    ->where(function ($query) use ($order) {
                        $query->where('production_order_id', $order->id)
                            ->orWhere(function ($query) use ($order) {
                                if ($order->sales_order_item_id) {
                                    $query->whereHas('materialRequirementItem', function ($q) use ($order) {
                                        $q->where('sales_order_item_id', $order->sales_order_item_id);
                                    });
                                }
                            });
                    })
                    ->get();

                foreach ($requests as $req) {
                    $req->update(['status' => 'completed']);
                    if ($req->materialRequirementItem) {
                        $req->materialRequirementItem->update(['status' => 'Ready']);
                    }
                }

                // Fallback for direct material requirement items update
                $items = MaterialRequirementItem::whereHas('materialRequirement', function ($query) use ($order) {
                    $query->where('tenant_id', $order->tenant_id);
                })
                    ->where(function ($query) use ($order) {
                        $query->where('production_order_id', $order->id)
                            ->orWhere(function ($query) use ($order) {
                                if ($order->sales_order_item_id) {
                                    $query->where('sales_order_item_id', $order->sales_order_item_id);
                                }
                            });
                    })
                    ->get();

                foreach ($items as $item) {
                    $item->update(['status' => 'Ready']);
                }
            }

            if ($order->wasChanged('status') && $order->status === self::STATUS_COMPLETED) {
                \App\Domains\Production\Events\ProductionOrderCompleted::dispatch($order);
            }
        });
    }

    public const STATUS_DRAFT = 'draft';

    /** UR-12 (M-24): a draft whose first material request has been sent — it can no longer be edited or deleted, only cancelled or released. */
    public const STATUS_LOCKED = 'locked';

    public const STATUS_RELEASED = 'released';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LOCKED,
        self::STATUS_RELEASED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    public const MODEL_PURE_MANUFACTURING = 'pure_manufacturing';
    public const MODEL_SUBCONTRACT_COMPLETE = 'subcontract_complete';
    public const MODEL_SUBCONTRACT_COMPANY_MATERIAL = 'subcontract_company_material';
    public const MODEL_HYBRID = 'hybrid';

    public const PRODUCTION_MODELS = [
        self::MODEL_PURE_MANUFACTURING,
        self::MODEL_SUBCONTRACT_COMPLETE,
        self::MODEL_SUBCONTRACT_COMPANY_MATERIAL,
        self::MODEL_HYBRID,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'order_number',
        'production_plan_id',
        'product_id',
        'bom_id',
        'routing_id',
        'quantity_ordered',
        'quantity_produced',
        'quantity_rejected',
        'quantity_scrapped',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'status',
        'production_model',
        'description',
        'created_by',
        'released_by',
        'completed_by',
        'closed_by',
        'released_at',
        'completed_at',
        'closed_at',
        'production_mode',
        'barcode',
        'qr_code',
        'sales_order_id',
        'sales_order_item_id',
        'project_id',
        'project_milestone_id',
        'project_task_id',
        'sub_assembly_plan',
    ];

    protected $casts = [
        'sub_assembly_plan' => 'array',
        'quantity_ordered' => 'float',
        'quantity_produced' => 'float',
        'quantity_rejected' => 'float',
        'quantity_scrapped' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'datetime',
        'actual_end_date' => 'datetime',
        'released_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Sales\Models\SalesOrder::class, 'sales_order_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Sales\Models\SalesOrderItem::class, 'sales_order_item_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Projects\Models\Project::class, 'project_id');
    }

    public function projectMilestone(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Projects\Models\Milestone::class, 'project_milestone_id');
    }

    public function projectTask(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Projects\Models\Task::class, 'project_task_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'bom_id')->withoutGlobalScopes();
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id')->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function operations(): HasMany
    {
        // UR-10: planned operations carry their execution order (sub-assembly operations first, then the operation that needs them);
        // orders created before that have none and keep the old order (deepest BOM level first, then sequence).
        return $this->hasMany(ProductionOrderOperation::class, 'production_order_id')
            ->orderByRaw('execution_order IS NULL')
            ->orderBy('execution_order', 'asc')
            ->orderBy('bom_level', 'desc')
            ->orderBy('sequence', 'asc')
            ->orderBy('id', 'asc');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ProductionOrderReservation::class, 'production_order_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ProductionOrderIssue::class, 'production_order_id');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ProductionOrderProgressLog::class, 'production_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ProductionOrderReceipt::class, 'production_order_id');
    }

    /** PX-19: finished goods already received into stock for this order. */
    public function receivedFgQuantity(): float
    {
        return (float) $this->receipts()->sum('quantity_received');
    }

    /** PX-19: good output produced but not yet received into stock. */
    public function pendingFgQuantity(): float
    {
        return max(0.0, round((float) $this->quantity_produced - $this->receivedFgQuantity(), 4));
    }

    public function scraps(): HasMany
    {
        return $this->hasMany(ProductionOrderScrap::class, 'production_order_id');
    }

    public function remnants(): HasMany
    {
        return $this->hasMany(\App\Domains\Inventory\Models\InventoryRemnant::class, 'source_production_order_id');
    }

    public function remnantAllocations(): HasMany
    {
        return $this->hasMany(ProductionOrderRemnantAllocation::class, 'production_order_id');
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(ProductionOrderRework::class, 'production_order_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'production_order_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(ProductionSerialNumber::class, 'production_order_id');
    }

    public function wips(): HasMany
    {
        return $this->hasMany(ProductionWip::class, 'production_order_id');
    }

    public function wipTransactions(): HasMany
    {
        return $this->hasMany(ProductionWipTransaction::class, 'production_order_id');
    }

    public function requisitionSlips(): HasMany
    {
        return $this->hasMany(ProductionRequisitionSlip::class, 'production_order_id');
    }

    public function costAdjustments(): HasMany
    {
        return $this->hasMany(ProductionCostAdjustment::class, 'production_order_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ProductionSchedule::class, 'production_order_id');
    }

    // ── Status Helpers ──

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /** Not released to the shop floor yet: a draft, or a locked draft (material requested). */
    public function isPreRelease(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_LOCKED], true);
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFrozen(): bool
    {
        return in_array($this->status, [
            self::STATUS_LOCKED,
            self::STATUS_RELEASED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * M-35: material has been requested from the store (a requisition slip that is not cancelled / rejected) AND the store has reserved
     * it for this order (or already issued it) — from then on the order cannot be edited any more, whatever its status.
     */
    public function materialsRequestedAndReserved(): bool
    {
        $requested = $this->requisitionSlips()
            ->whereNotIn('status', ['cancelled', 'Cancelled', 'rejected', 'Rejected'])
            ->exists();
        if (!$requested) {
            return false;
        }

        return $this->reservations()
            ->where(fn ($q) => $q->where('quantity_reserved', '>', 0)->orWhere('quantity_issued', '>', 0))
            ->exists();
    }

    /**
     * Why this order cannot be edited (M-36: or deleted — the two rules are the same) right now; null = it can. One place for the Edit
     * screen, the save, the delete, the API and the buttons.
     *
     * @param string $action 'edited' or 'deleted' — only used to word the message
     */
    public function editBlockReason(string $action = 'edited'): ?string
    {
        if ($this->isLocked()) {
            return "This order is locked (a material request was sent): it can no longer be {$action} — only cancelled or released.";
        }
        if ($this->isFrozen()) {
            return "Frozen Production Orders cannot be {$action}.";
        }
        if ($this->materialsRequestedAndReserved()) {
            return "Material has been requested from the store and is reserved for this order, so it can no longer be {$action}.";
        }

        return null;
    }

    // ── Production Model Helpers ──

    public function isPureManufacturing(): bool
    {
        return ($this->production_model ?? self::MODEL_PURE_MANUFACTURING) === self::MODEL_PURE_MANUFACTURING;
    }

    public function isSubcontractComplete(): bool
    {
        return $this->production_model === self::MODEL_SUBCONTRACT_COMPLETE;
    }

    public function isSubcontractCompanyMaterial(): bool
    {
        return $this->production_model === self::MODEL_SUBCONTRACT_COMPANY_MATERIAL;
    }

    public function isHybrid(): bool
    {
        return $this->effective_production_model === self::MODEL_HYBRID;
    }

    public static function determineProductionModelFromOperations($operations, ?string $fallback = null): string
    {
        $collection = collect($operations);
        if ($collection->isEmpty()) {
            return $fallback ?? self::MODEL_PURE_MANUFACTURING;
        }

        $hasExt = $collection->contains('is_external', true);
        $hasInt = $collection->contains(fn($op) => !($op->is_external ?? false));

        if ($hasExt && $hasInt) {
            return self::MODEL_HYBRID;
        }

        if ($hasExt && !$hasInt) {
            // SF-2: fully vendor work = every outsourced op has the BOM "Vendor-supplied materials" tick
            $allVendorSupplied = $collection->every(fn($op) => (bool) ($op->vendor_supplies_materials ?? false));
            if ($allVendorSupplied) {
                return in_array($fallback, ['complete_subcontracting', self::MODEL_SUBCONTRACT_COMPLETE], true)
                    ? $fallback
                    : self::MODEL_SUBCONTRACT_COMPLETE;
            }
            return self::MODEL_SUBCONTRACT_COMPANY_MATERIAL;
        }

        return self::MODEL_PURE_MANUFACTURING;
    }

    public function getEffectiveProductionModelAttribute(): string
    {
        $ops = $this->relationLoaded('operations') ? $this->operations : $this->operations()->get();
        if ($ops && $ops->isNotEmpty()) {
            return self::determineProductionModelFromOperations($ops);
        }

        return $this->production_model ?? self::MODEL_PURE_MANUFACTURING;
    }
}
