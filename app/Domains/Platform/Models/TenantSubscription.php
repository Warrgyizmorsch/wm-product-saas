<?php

namespace App\Domains\Platform\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant's recurring per-user subscription, mirrored from the gateway
 * (Razorpay Subscriptions) — see TenantSubscriptionService.
 */
class TenantSubscription extends BaseModel
{
    public const STATUS_CREATED = 'created';     // checkout started, not paid yet
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING = 'pending';     // a renewal charge failed, gateway retrying
    public const STATUS_HALTED = 'halted';       // gateway gave up retrying
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    /** Statuses in which the subscription is still the tenant's live one. */
    public const LIVE_STATUSES = [self::STATUS_ACTIVE, self::STATUS_PENDING, self::STATUS_HALTED];

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'cycle',
        'seats',
        'modules',
        'pending_change',
        'subtotal',
        'gst',
        'total',
        'currency',
        'gateway',
        'gateway_plan_id',
        'gateway_subscription_id',
        'status',
        'activated_at',
        'current_start',
        'current_end',
        'grace_ends_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'modules' => 'array',
            'pending_change' => 'array',
            'subtotal' => 'integer',
            'gst' => 'integer',
            'total' => 'integer',
            'activated_at' => 'datetime',
            'current_start' => 'datetime',
            'current_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE_STATUSES, true);
    }

    /** A downgrade booked for the next renewal (not an upgrade still awaiting payment). */
    public function scheduledChange(): ?array
    {
        $change = $this->pending_change;

        return $change !== null && empty($change['payment_id']) ? $change : null;
    }

    /** Per-seat amount the gateway charges each cycle, GST included (paise). */
    public function perSeatTotal(): int
    {
        return intdiv($this->total, max(1, $this->seats));
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
