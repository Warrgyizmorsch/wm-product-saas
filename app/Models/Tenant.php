<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_TRIAL = 'trial';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ARCHIVED = 'archived';

    public const PLAN_STARTER = 'starter';
    public const PLAN_PRO = 'pro';
    public const PLAN_ENTERPRISE = 'enterprise';

    public const SUBSCRIPTION_TRIAL = 'trial';
    public const SUBSCRIPTION_ACTIVE = 'active';
    public const SUBSCRIPTION_PAST_DUE = 'past_due';
    public const SUBSCRIPTION_CANCELLED = 'cancelled';

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'domain',
        'billing_email',
        'status',
        'plan',
        'subscription_status',
        'max_users',
        'max_storage_mb',
        'trial_ends_at',
        'plan_started_at',
        'plan_expires_at',
        'onboarded_at',
        'archived_at',
        'timezone',
        'locale',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'plan_started_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'archived_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_TRIAL => 'Trial',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAST_DUE => 'Past Due',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }

    public static function accessibleStatuses(): array
    {
        return [
            self::STATUS_TRIAL,
            self::STATUS_ACTIVE,
        ];
    }

    public static function plans(): array
    {
        return [
            self::PLAN_STARTER => 'Starter',
            self::PLAN_PRO => 'Pro',
            self::PLAN_ENTERPRISE => 'Enterprise',
        ];
    }

    public static function subscriptionStatuses(): array
    {
        return [
            self::SUBSCRIPTION_TRIAL => 'Trial',
            self::SUBSCRIPTION_ACTIVE => 'Active',
            self::SUBSCRIPTION_PAST_DUE => 'Past Due',
            self::SUBSCRIPTION_CANCELLED => 'Cancelled',
        ];
    }

    public function isAccessible(): bool
    {
        return in_array($this->status, self::accessibleStatuses(), true);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
