<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Routing extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'routings';

    // Status constants — no magic strings in application code
    public const STATUS_DRAFT            = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_ACTIVE           = 'active';
    public const STATUS_HISTORICAL       = 'historical';
    public const STATUS_CANCELLED        = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_ACTIVE,
        self::STATUS_HISTORICAL,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
        'routing_number',
        'name',
        'product_id',
        'version',
        'revision',
        'is_default',       // A3: routing alternatives flag
        'effective_from',
        'effective_to',
        'status',
        'description',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'approved_at'    => 'datetime',
        'revision'       => 'integer',
        'is_default'     => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'routing_id')
            ->orderBy('sequence');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RoutingApproval::class, 'routing_id')
            ->orderBy('created_at', 'desc');
    }

    public function boms(): HasMany
    {
        return $this->hasMany(ProductionBom::class, 'routing_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isHistorical(): bool
    {
        return $this->status === self::STATUS_HISTORICAL;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function isReadOnly(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACTIVE,
            self::STATUS_HISTORICAL,
            self::STATUS_CANCELLED,
        ], true);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Active routing: status=active and current date falls within effective range.
     */
    public function scopeActive(Builder $query): void
    {
        $today = Carbon::today()->toDateString();
        $query->where('status', self::STATUS_ACTIVE)
            ->where('effective_from', '<=', $today)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $today);
            });
    }

    public function scopePrimary(Builder $query): void
    {
        $query->where('is_default', true);
    }

    public function scopeForProduct(Builder $query, int $productId): void
    {
        $query->where('product_id', $productId);
    }

    // ─── Computed Helpers ─────────────────────────────────────────────────────

    /**
     * Total estimated cycle time for this routing in minutes.
     * Future: used by SchedulingEngine.
     */
    public function totalCycleTimeMinutes(): float
    {
        return $this->operations->sum(function (RoutingOperation $op): float {
            return $op->setup_time_minutes + $op->processing_time_minutes + $op->wait_time_minutes;
        });
    }

    /**
     * Operation count (non-deleted).
     */
    public function operationCount(): int
    {
        return $this->operations()->count();
    }
}
