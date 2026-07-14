<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderItem;
use App\Models\Concerns\Loggable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionPlan extends BaseModel
{
    use HasFactory, Loggable, SoftDeletes;

    protected $table = 'production_plans';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_MRP_GENERATED = 'mrp_generated';

    public const STATUS_RELEASED = 'released';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_MRP_GENERATED,
        self::STATUS_RELEASED,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
        'plan_number',
        'name',
        'product_id',
        'bom_id',
        'routing_id',
        'sales_order_id',
        'sales_order_item_id',
        'quantity',
        'start_date',
        'end_date',
        'status',
        'description',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'quantity' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'bom_id')->withoutGlobalScopes();
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id')->withoutGlobalScopes();
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProductionPlanRequirement::class, 'production_plan_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(ProductionPlanOperation::class, 'production_plan_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isMrpGenerated(): bool
    {
        return $this->status === self::STATUS_MRP_GENERATED;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
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

    /**
     * Checks if the plan details are frozen (read-only due to workflow progression).
     */
    public function isFrozen(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_MRP_GENERATED,
            self::STATUS_RELEASED,
            self::STATUS_COMPLETED,
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
        ]);
    }
}
