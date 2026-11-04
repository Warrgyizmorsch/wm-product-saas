<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One external company (a Tally company, say) linked to this tenant's books. */
class IntegrationConnection extends BaseModel
{
    use SoftDeletes;

    public const PRODUCT_TALLY = 'tally';
    public const PRODUCT_CUSTOM = 'custom';
    public const PRODUCTS = [self::PRODUCT_TALLY, self::PRODUCT_CUSTOM];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';

    protected $table = 'accounting_integration_connections';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'product',
        'external_company',
        'status',
        'settings',
        'last_agent_seen_at',
        'agent_version',
        'last_planned_at',
        'first_inbound_completed_at',
        'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'last_agent_seen_at' => 'datetime',
        'last_planned_at' => 'datetime',
        'first_inbound_completed_at' => 'datetime',
    ];

    public function refs(): HasMany
    {
        return $this->hasMany(IntegrationRef::class, 'connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    /** The Sanctum ability that ties a token to this connection alone. */
    public function tokenAbility(): string
    {
        return self::abilityPrefix().$this->id;
    }

    public static function abilityPrefix(): string
    {
        return 'accounting-integration:connection:';
    }
}
