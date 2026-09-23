<?php

namespace App\Domains\Platform\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A module a tenant bought as a one-time add-on (see TenantModuleService).
 * Active while uninstalled_at is null; an uninstalled row is kept so the
 * tenant can reinstall for free.
 */
class TenantModule extends BaseModel
{
    /** Bought with the old one-time fee — free for life, never billed. */
    public const BILLING_LIFETIME = 'lifetime';

    /** Billed per user every cycle on the tenant's subscription. */
    public const BILLING_RECURRING = 'recurring';

    protected $fillable = [
        'tenant_id',
        'module',
        'billing',
        'subscription_payment_id',
        'installed_at',
        'installed_by',
        'uninstalled_at',
        'uninstalled_by',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->uninstalled_at === null;
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }
}
