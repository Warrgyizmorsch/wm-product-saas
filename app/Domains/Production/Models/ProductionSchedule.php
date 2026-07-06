<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionSchedule extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_schedules';

    // ─── Status Constants (Planning Lifecycle Only) ───────────────────────────
    // NOTE: 'running' and 'paused' are MES operation statuses — never schedule statuses.

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RELEASED  = 'released';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SCHEDULED,
        self::STATUS_RELEASED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    // ─── Scheduling Type Constants ────────────────────────────────────────────

    public const TYPE_FORWARD  = 'forward';
    public const TYPE_BACKWARD = 'backward';
    public const TYPE_MANUAL   = 'manual';

    public const SCHEDULING_TYPES = [
        self::TYPE_FORWARD,
        self::TYPE_BACKWARD,
        self::TYPE_MANUAL,
    ];

    // ─── Fillable ─────────────────────────────────────────────────────────────

    protected $fillable = [
        'tenant_id',
        'schedule_number',
        'production_order_id',
        'scheduling_type',
        'status',
        'scheduled_at',
        'released_at',
        'completed_at',
        'cancelled_at',
        'created_by',
        'released_by',
        'completed_by',
        'cancelled_by',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'released_at'  => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(ProductionScheduleOperation::class, 'production_schedule_id')
            ->orderBy('sequence');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * A frozen schedule cannot be edited.
     */
    public function isFrozen(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ]);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_RELEASED]);
    }

    public function scopeReleased(Builder $query): void
    {
        $query->where('status', self::STATUS_RELEASED);
    }
}
