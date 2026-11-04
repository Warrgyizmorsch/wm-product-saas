<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something the sync will not decide alone. Nothing is overwritten until a person chooses. */
class IntegrationConflict extends BaseModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';

    public const RESOLUTION_APPLY_INCOMING = 'apply_incoming';
    public const RESOLUTION_KEEP_LOCAL = 'keep_local';

    public const REASON_CHANGED_ON_BOTH_SIDES = 'changed_on_both_sides';
    public const REASON_TYPE_CHANGE = 'account_type_change';
    public const REASON_SYSTEM_ACCOUNT = 'system_account_change';
    public const REASON_CANNOT_REVERSE = 'cannot_reverse_existing';
    public const REASON_AWAITING_APPROVAL = 'existing_awaiting_approval';
    public const REASON_REVERSED_LOCALLY = 'reversed_locally';
    public const REASON_REJECTED_LOCALLY = 'rejected_locally';
    public const REASON_MISSING_IN_SOURCE = 'missing_in_source';

    protected $table = 'accounting_integration_conflicts';

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'ref_id',
        'entity_type',
        'external_id',
        'reason',
        'detail',
        'incoming_payload',
        'incoming_hash',
        'status',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'incoming_payload' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function ref(): BelongsTo
    {
        return $this->belongsTo(IntegrationRef::class, 'ref_id');
    }
}
