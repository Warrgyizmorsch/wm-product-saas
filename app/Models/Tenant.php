<?php

namespace App\Models;

use App\Domains\Platform\Models\Plan;
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
        'plan_id',
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

    public function planCatalog(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Modules the tenant's plan includes plus any self-service add-ons it has
     * installed (see installModules()), or null when there is no plan/feature
     * list (everything is allowed). tenant_allowed_modules() delegates here.
     *
     * @return array<int, string>|null
     */
    public function planModules(): ?array
    {
        $features = $this->planCatalog?->features;

        if ($features === null) {
            return null;
        }

        $installed = $this->settings['installed_modules'] ?? [];

        return array_values(array_unique([...$features, ...$installed]));
    }

    /** True when the plan includes ANY of the given modules (or has no module limit). */
    public function hasModule(string ...$modules): bool
    {
        $allowed = $this->planModules();

        return $allowed === null || array_intersect($modules, $allowed) !== [];
    }

    /**
     * Self-service module add-ons (see TenantModuleController): grants these modules
     * to this tenant only, on top of its plan, without touching the Plan row every
     * other tenant on that plan shares. Merged into tenant_allowed_modules().
     */
    public function installModules(array $modules): void
    {
        if ($modules === []) {
            return;
        }

        $settings = $this->settings ?? [];
        $installed = $settings['installed_modules'] ?? [];
        $settings['installed_modules'] = array_values(array_unique([...$installed, ...$modules]));

        $this->update(['settings' => $settings]);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
