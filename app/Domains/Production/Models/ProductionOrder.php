<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_orders';

    public const STATUS_DRAFT         = 'draft';
    public const STATUS_RELEASED      = 'released';
    public const STATUS_IN_PROGRESS   = 'in_progress';
    public const STATUS_COMPLETED     = 'completed';
    public const STATUS_CLOSED        = 'closed';
    public const STATUS_CANCELLED     = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RELEASED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
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
    ];

    protected $casts = [
        'quantity_ordered'  => 'float',
        'quantity_produced' => 'float',
        'quantity_rejected' => 'float',
        'quantity_scrapped' => 'float',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'actual_start_date' => 'datetime',
        'actual_end_date'   => 'datetime',
        'released_at'       => 'datetime',
        'completed_at'      => 'datetime',
        'closed_at'         => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

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
        return $this->hasMany(ProductionOrderOperation::class, 'production_order_id')->orderBy('sequence');
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

    public function scraps(): HasMany
    {
        return $this->hasMany(ProductionOrderScrap::class, 'production_order_id');
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

    // ── Status Helpers ──

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
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
            self::STATUS_RELEASED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
        ]);
    }
}
