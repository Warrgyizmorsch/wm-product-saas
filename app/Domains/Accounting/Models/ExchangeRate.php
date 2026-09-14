<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 unit of from_currency = rate units of to_currency, effective from
 * effective_date until a later rate for the same pair supersedes it.
 *
 * Tenant-wide, not company-scoped (no BelongsToCompany): a market rate is the
 * same fact for every company in the tenant, whatever their base currency.
 */
class ExchangeRate extends BaseModel
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_API = 'api';

    protected $table = 'exchange_rates';

    protected $fillable = [
        'tenant_id',
        'from_currency',
        'to_currency',
        'rate',
        'effective_date',
        'source',
        'created_by',
    ];

    protected $casts = [
        'rate' => 'float',
        'effective_date' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSynced(): bool
    {
        return $this->source === self::SOURCE_API;
    }
}
