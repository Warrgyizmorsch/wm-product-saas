<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request the on-premise relay should send to Tally, and report back on. */
class IntegrationAgentJob extends BaseModel
{
    public const KIND_PULL_GROUPS = 'pull_groups';
    public const KIND_PULL_LEDGERS = 'pull_ledgers';
    public const KIND_PULL_VOUCHERS = 'pull_vouchers';
    public const KIND_PUSH_LEDGER = 'push_ledger';
    public const KIND_PUSH_VOUCHER = 'push_voucher';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_LEASED = 'leased';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'accounting_integration_agent_jobs';

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'batch_id',
        'kind',
        'params',
        'request_xml',
        'status',
        'attempts',
        'leased_until',
        'last_error',
        'completed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'leased_until' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }
}
