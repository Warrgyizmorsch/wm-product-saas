<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * External id <-> local record. One row per record per connection, enforced by a
 * unique index, so a re-sync always finds what it did last time.
 */
class IntegrationRef extends BaseModel
{
    public const ENTITY_GROUP = 'ledger_group';
    public const ENTITY_LEDGER = 'ledger';
    public const ENTITY_VOUCHER = 'voucher';
    public const ENTITIES = [self::ENTITY_GROUP, self::ENTITY_LEDGER, self::ENTITY_VOUCHER];

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const LOCAL_GROUP = 'ledger_group';
    public const LOCAL_ACCOUNT = 'chart_of_account';
    public const LOCAL_JOURNAL = 'journal';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_FAILED = 'failed';

    /** Value of journals.reference_type for a journal imported through a ref. */
    public const JOURNAL_REFERENCE_TYPE = 'accounting_integration_ref';

    protected $table = 'accounting_integration_refs';

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'direction',
        'entity_type',
        'external_id',
        'external_name',
        'external_number',
        'external_date',
        'local_type',
        'local_id',
        'payload_hash',
        'local_hash',
        'meta',
        'status',
        'last_error',
        'last_synced_at',
    ];

    protected $casts = [
        'external_date' => 'date',
        'meta' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }
}
